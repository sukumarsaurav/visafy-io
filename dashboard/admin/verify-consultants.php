<?php
// Set page title
$page_title = "Verify Consultants";

// Include header
include('includes/header.php');

// Get all consultants who have uploaded verification documents but are not verified yet
$query = "SELECT 
    u.id AS consultant_id,
    u.first_name,
    u.last_name,
    u.email,
    u.profile_picture,
    c.company_name,
    COALESCE(cp.is_verified, 0) AS is_verified,
    COUNT(cv.id) AS document_count
FROM 
    users u
JOIN 
    consultants c ON u.id = c.user_id
LEFT JOIN 
    consultant_profiles cp ON u.id = cp.consultant_id
LEFT JOIN 
    consultant_verifications cv ON u.id = cv.consultant_id
WHERE 
    u.user_type = 'consultant' 
    AND u.deleted_at IS NULL
    AND u.status = 'active'
    AND (cp.is_verified = 0 OR cp.is_verified IS NULL)
GROUP BY 
    u.id
ORDER BY 
    document_count DESC";

$result = $conn->query($query);
$consultants = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $consultants[] = $row;
    }
}

// Process verification action if submitted
$action_message = '';
$action_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_consultant'])) {
    $verify_id = intval($_POST['consultant_id']);
    
    if ($verify_id > 0) {
        // Check if consultant profile exists
        $check_profile = $conn->prepare("SELECT consultant_id FROM consultant_profiles WHERE consultant_id = ?");
        $check_profile->bind_param("i", $verify_id);
        $check_profile->execute();
        $profile_result = $check_profile->get_result();
        
        if ($profile_result->num_rows > 0) {
            // Update existing profile
            $stmt = $conn->prepare("UPDATE consultant_profiles SET is_verified = 1, verified_at = NOW(), verified_by = ? WHERE consultant_id = ?");
            $stmt->bind_param("ii", $user_id, $verify_id);
        } else {
            // Create new profile entry
            $stmt = $conn->prepare("INSERT INTO consultant_profiles (consultant_id, is_verified, verified_at, verified_by) VALUES (?, 1, NOW(), ?)");
            $stmt->bind_param("ii", $verify_id, $user_id);
        }
        
        if ($stmt->execute()) {
            // Also mark all documents as verified
            $update_docs = $conn->prepare("UPDATE consultant_verifications SET verified = 1, verified_at = NOW() WHERE consultant_id = ?");
            $update_docs->bind_param("i", $verify_id);
            $update_docs->execute();
            
            $action_message = "Consultant has been verified successfully.";
        } else {
            $action_error = "Failed to verify consultant: " . $conn->error;
        }
        $stmt->close();
    }
}
?>

<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner">
        <div class="spinner"></div>
    </div>
</div>

<div class="container-fluid" id="pageContent" style="display: none;">
    <?php if (!empty($action_message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo $action_message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($action_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $action_error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Verify Consultants</h1>
    </div>

    <?php if (empty($consultants)): ?>
    <div class="card shadow mb-4">
        <div class="card-body">
            <p class="text-center">No consultants pending verification at this time.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Consultants Pending Verification</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Consultant</th>
                            <th>Email</th>
                            <th>Company</th>
                            <th>Documents</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consultants as $consultant): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php 
                                    $profile_img = '../assets/images/default-consultant.svg';
                                    if (!empty($consultant['profile_picture'])) {
                                        if (file_exists('../../uploads/users/' . $consultant['consultant_id'] . '/profile/' . $consultant['profile_picture'])) {
                                            $profile_img = '../../uploads/users/' . $consultant['consultant_id'] . '/profile/' . $consultant['profile_picture'];
                                        }
                                    }
                                    ?>
                                    
                                    <img src="<?php echo $profile_img; ?>" class="rounded-circle mr-2" width="40" height="40" alt="Profile">
                                    <div>
                                        <?php echo htmlspecialchars($consultant['first_name'] . ' ' . $consultant['last_name']); ?>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($consultant['email']); ?></td>
                            <td><?php echo htmlspecialchars($consultant['company_name']); ?></td>
                            <td>
                                <span class="badge bg-info text-white"><?php echo $consultant['document_count']; ?> documents</span>
                            </td>
                            <td>
                                <a href="view-consultant.php?id=<?php echo $consultant['consultant_id']; ?>" class="btn btn-info btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#verifyModal<?php echo $consultant['consultant_id']; ?>">
                                    <i class="fas fa-check"></i> Verify
                                </button>
                                
                                <!-- Verify Modal -->
                                <div class="modal fade" id="verifyModal<?php echo $consultant['consultant_id']; ?>" tabindex="-1" aria-labelledby="verifyModalLabel" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="verifyModalLabel">Confirm Verification</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to verify <strong><?php echo htmlspecialchars($consultant['first_name'] . ' ' . $consultant['last_name']); ?></strong>?</p>
                                                <p>This will mark their profile as verified and display a verification badge on their profile.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form method="post">
                                                    <input type="hidden" name="consultant_id" value="<?php echo $consultant['consultant_id']; ?>">
                                                    <button type="submit" name="verify_consultant" class="btn btn-success">Verify Consultant</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
/* Loading Animation Styles */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.9);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.loading-spinner {
    text-align: center;
}

.spinner {
    width: 50px;
    height: 50px;
    border: 4px solid var(--light-color);
    border-top: 4px solid var(--primary-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 15px;
}

.loading-spinner p {
    color: var(--primary-color);
    font-size: 16px;
    font-weight: 500;
    margin: 0;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Fade In Animation */
.fade-in {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Layout & Cards */
.container-fluid {
    padding: 20px;
}

.card {
    border: 1px solid var(--border-color);
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(4, 33, 103, 0.05);
}

.card-header {
    background-color: var(--light-color);
    border-bottom: 1px solid var(--border-color);
    padding: 15px 20px;
}

.card-header h6 {
    margin: 0;
    color: var(--primary-color);
    font-weight: 700;
}

.card-body {
    padding: 20px;
}

/* Table */
.table thead th {
    background-color: var(--light-color);
    color: var(--primary-color);
    font-weight: 600;
    border-bottom: 1px solid var(--border-color);
}

.table td, .table th {
    vertical-align: middle;
    color: var(--dark-color);
}

/* Badges & Buttons */
.badge {
    font-size: 12px;
    font-weight: 600;
    padding: 6px 10px;
    border-radius: 999px;
}

.btn {
    border-radius: 6px;
    font-weight: 600;
}

.btn-info {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.btn-info:hover {
    background-color: #021646;
    border-color: #021646;
}

.btn-success {
    background-color: var(--success-color);
    border-color: var(--success-color);
}

.btn-success:hover {
    background-color: #149c6c;
    border-color: #149c6c;
}

/* Table spacing in responsive wrapper */
.table-responsive {
    padding: 0;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show loading overlay
    const loadingOverlay = document.getElementById('loadingOverlay');
    const pageContent = document.getElementById('pageContent');
    
    // Function to check if all images are loaded
    function areImagesLoaded() {
        const images = document.getElementsByTagName('img');
        for (let img of images) {
            if (!img.complete) {
                return false;
            }
        }
        return true;
    }
    
    // Function to show the page content
    function showContent() {
        loadingOverlay.style.display = 'none';
        pageContent.style.display = 'block';
        pageContent.classList.add('fade-in');
        
        // Initialize DataTable after content is shown
        if ($.fn.DataTable) {
            $('#dataTable').DataTable({
                "pageLength": 25,
                "language": {
                    "emptyTable": "No consultants pending verification"
                }
            });
        }
    }
    
    // Check if all assets are loaded
    window.onload = function() {
        if (areImagesLoaded()) {
            // Add a small delay for smoother transition
            setTimeout(showContent, 500);
        } else {
            // If images are not loaded, wait for them
            const images = document.getElementsByTagName('img');
            let loadedImages = 0;
            
            function imageLoaded() {
                loadedImages++;
                if (loadedImages === images.length) {
                    setTimeout(showContent, 500);
                }
            }
            
            for (let img of images) {
                if (img.complete) {
                    imageLoaded();
                } else {
                    img.addEventListener('load', imageLoaded);
                    img.addEventListener('error', imageLoaded); // Handle error cases
                }
            }
        }
    };
    
    // Fallback: Show content if loading takes too long
    setTimeout(showContent, 3000);
});
</script>

<?php
// Include footer
include('includes/footer.php');
?>