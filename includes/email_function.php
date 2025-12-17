<?php
/**
 * Function to send emails
 *
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body
 * @param array $options Optional parameters like CC, BCC, reply-to, etc.
 * @return bool True if email sent successfully, false otherwise
 */
function send_email($to, $subject, $body, $options = []) {
    // Load email configuration
    require_once __DIR__ . '/../config/email_config.php';
    
    // Default options
    $default_options = [
        'from_email' => EMAIL_FROM,
        'from_name' => EMAIL_FROM_NAME,
        'reply_to' => EMAIL_REPLY_TO,
        'cc' => [],
        'bcc' => [],
        'is_html' => false
    ];
    
    // Merge default options with provided options
    $opts = array_merge($default_options, $options);
    
    // Set up email headers
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = $opts['is_html'] ? 'Content-type: text/html; charset=UTF-8' : 'Content-type: text/plain; charset=UTF-8';
    $headers[] = 'From: ' . $opts['from_name'] . ' <' . $opts['from_email'] . '>';
    $headers[] = 'Reply-To: ' . $opts['reply_to'];
    
    // Add CC recipients
    if (!empty($opts['cc'])) {
        foreach ($opts['cc'] as $cc) {
            $headers[] = 'Cc: ' . $cc;
        }
    }
    
    // Add BCC recipients
    if (!empty($opts['bcc'])) {
        foreach ($opts['bcc'] as $bcc) {
            $headers[] = 'Bcc: ' . $bcc;
        }
    }
    
    // Attempt to send email
    return mail($to, $subject, $body, implode("\r\n", $headers));
}

/**
 * Function to send HTML emails with template
 *
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $template Path to the HTML template file
 * @param array $variables Variables to replace in the template
 * @param array $options Optional parameters like CC, BCC, reply-to, etc.
 * @return bool True if email sent successfully, false otherwise
 */
function send_template_email($to, $subject, $template, $variables = [], $options = []) {
    // Set HTML option to true
    $options['is_html'] = true;
    
    // Check if template exists
    if (!file_exists($template)) {
        return false;
    }
    
    // Get template content
    $body = file_get_contents($template);
    
    // Replace variables in template
    foreach ($variables as $key => $value) {
        $body = str_replace('{{' . $key . '}}', $value, $body);
    }
    
    // Send email
    return send_email($to, $subject, $body, $options);
} 