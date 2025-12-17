CREATE TRIGGER `after_booking_confirmed` AFTER UPDATE ON `bookings`
 FOR EACH ROW BEGIN
    DECLARE v_status_name VARCHAR(50);
    DECLARE v_conversation_exists INT;
    
    -- Get the status name
    SELECT name INTO v_status_name 
    FROM booking_statuses 
    WHERE id = NEW.status_id;
    
    -- Check if booking status changed to confirmed and no conversation exists yet
    IF v_status_name = 'confirmed' AND (OLD.status_id != NEW.status_id) THEN
        -- Check if conversation already exists for this booking
        SELECT COUNT(*) INTO v_conversation_exists 
        FROM conversations 
        WHERE booking_id = NEW.id;
        
        -- If no conversation exists, create one
        IF v_conversation_exists = 0 THEN
            CALL create_booking_conversation(NEW.id, @conversation_id);
        END IF;
    END IF;
END

CREATE TRIGGER `after_booking_status_change` AFTER UPDATE ON `bookings`
 FOR EACH ROW BEGIN
    DECLARE v_status_name VARCHAR(50);
    DECLARE v_service_name VARCHAR(255);
    DECLARE v_user_name VARCHAR(255);
    DECLARE v_consultant_name VARCHAR(255);
    DECLARE v_notification_id INT;
    DECLARE v_template_id INT;
    
    -- Check if status has changed
    IF NEW.status_id != OLD.status_id THEN
        -- Get status name
        SELECT name INTO v_status_name FROM booking_statuses WHERE id = NEW.status_id;
        
        -- Get service name
        SELECT st.service_name INTO v_service_name
        FROM visa_services vs
        JOIN service_types st ON vs.service_type_id = st.service_type_id
        WHERE vs.visa_service_id = NEW.visa_service_id;
        
        -- Get user and consultant names
        SELECT CONCAT(first_name, ' ', last_name) INTO v_user_name
        FROM users WHERE id = NEW.user_id;
        
        SELECT CONCAT(first_name, ' ', last_name) INTO v_consultant_name
        FROM users WHERE id = NEW.consultant_id;
        
        -- Handle status changes with appropriate notifications
        CASE v_status_name
            WHEN 'confirmed' THEN
                -- Notify client
                CALL send_notification(
                    'booking_confirmed',
                    NEW.user_id,
                    CONCAT('Your booking for ', v_service_name, ' is confirmed'),
                    CONCAT('Your booking on ', DATE_FORMAT(NEW.booking_datetime, '%Y-%m-%d'), ' at ', 
                           TIME_FORMAT(NEW.booking_datetime, '%H:%i'), ' has been confirmed.'),
                    CONCAT('/dashboard/bookings/', NEW.id),
                    NEW.id, NULL, NULL, NULL, NULL, NULL,
                    NEW.organization_id,
                    NEW.consultant_id,
                    v_notification_id
                );
                
                -- Notify consultant
                CALL send_notification(
                    'booking_confirmed',
                    NEW.consultant_id,
                    CONCAT('Booking with ', v_user_name, ' confirmed'),
                    CONCAT('Your booking with ', v_user_name, ' on ', 
                           DATE_FORMAT(NEW.booking_datetime, '%Y-%m-%d'), ' at ', 
                           TIME_FORMAT(NEW.booking_datetime, '%H:%i'), ' is confirmed.'),
                    CONCAT('/dashboard/consultant/bookings/', NEW.id),
                    NEW.id, NULL, NULL, NULL, NULL, NULL,
                    NEW.organization_id,
                    NULL,
                    v_notification_id
                );
                
            WHEN 'cancelled_by_user' THEN
                -- Notify consultant
                CALL send_notification(
                    'booking_cancelled',
                    NEW.consultant_id,
                    CONCAT('Booking cancelled by ', v_user_name),
                    CONCAT('The booking with ', v_user_name, ' on ', 
                           DATE_FORMAT(NEW.booking_datetime, '%Y-%m-%d'), ' at ', 
                           TIME_FORMAT(NEW.booking_datetime, '%H:%i'), ' has been cancelled by the client.'),
                    CONCAT('/dashboard/consultant/bookings/', NEW.id),
                    NEW.id, NULL, NULL, NULL, NULL, NULL,
                    NEW.organization_id,
                    NEW.user_id,
                    v_notification_id
                );
                
            WHEN 'cancelled_by_consultant' THEN
                -- Notify client
                CALL send_notification(
                    'booking_cancelled',
                    NEW.user_id,
                    CONCAT('Booking cancelled by ', v_consultant_name),
                    CONCAT('Your booking for ', v_service_name, ' on ', 
                           DATE_FORMAT(NEW.booking_datetime, '%Y-%m-%d'), ' at ', 
                           TIME_FORMAT(NEW.booking_datetime, '%H:%i'), ' has been cancelled by the consultant.'),
                    CONCAT('/dashboard/bookings/', NEW.id),
                    NEW.id, NULL, NULL, NULL, NULL, NULL,
                    NEW.organization_id,
                    NEW.consultant_id,
                    v_notification_id
                );
                
            WHEN 'completed' THEN
                -- Notify client to leave feedback
                CALL send_notification(
                    'booking_completed',
                    NEW.user_id,
                    CONCAT('Your booking with ', v_consultant_name, ' is complete'),
                    CONCAT('Your booking for ', v_service_name, ' has been completed. Please take a moment to leave feedback.'),
                    CONCAT('/dashboard/bookings/', NEW.id, '/feedback'),
                    NEW.id, NULL, NULL, NULL, NULL, NULL,
                    NEW.organization_id,
                    NEW.completed_by,
                    v_notification_id
                );
        END CASE;
    END IF;
END

CREATE TRIGGER `before_booking_insert` BEFORE INSERT ON `bookings`
 FOR EACH ROW BEGIN
    DECLARE v_reference VARCHAR(20);
    
    IF NEW.reference_number IS NULL OR NEW.reference_number = '' THEN
        -- Generate a simple reference without calling the procedure
        SET NEW.reference_number = CONCAT('BK', DATE_FORMAT(NOW(), '%y'), LPAD(FLOOR(RAND() * 100000000), 8, '0'));
    END IF;
    
    -- Set end_datetime based on duration_minutes during INSERT
    SET NEW.end_datetime = DATE_ADD(NEW.booking_datetime, INTERVAL NEW.duration_minutes MINUTE);
END

CREATE TRIGGER `update_slot_bookings_after_insert` AFTER INSERT ON `bookings`
 FOR EACH ROW BEGIN
    -- Find the slot that matches this booking
    UPDATE service_availability_slots
    SET current_bookings = current_bookings + 1
    WHERE consultant_id = NEW.consultant_id
    AND visa_service_id = NEW.visa_service_id
    AND slot_date = DATE(NEW.booking_datetime)
    AND start_time <= TIME(NEW.booking_datetime)
    AND end_time >= TIME(NEW.end_datetime);
END

CREATE TRIGGER `update_slot_bookings_after_update` AFTER UPDATE ON `bookings`
 FOR EACH ROW BEGIN
    -- If booking was cancelled or deleted
    IF (NEW.deleted_at IS NOT NULL AND OLD.deleted_at IS NULL) OR 
       (NEW.status_id != OLD.status_id AND (
           SELECT name FROM booking_statuses WHERE id = NEW.status_id
       ) IN ('cancelled_by_user', 'cancelled_by_admin', 'cancelled_by_consultant')) THEN
        
        -- Decrease the booking count
        UPDATE service_availability_slots
        SET current_bookings = GREATEST(0, current_bookings - 1)
        WHERE consultant_id = NEW.consultant_id
        AND visa_service_id = NEW.visa_service_id
        AND slot_date = DATE(NEW.booking_datetime)
        AND start_time <= TIME(NEW.booking_datetime)
        AND end_time >= TIME(NEW.end_datetime);
    END IF;
END
