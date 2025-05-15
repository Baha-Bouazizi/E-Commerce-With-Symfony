<?php
namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class SmtpMailService
{
    private $fromAddress;
    private $fromName;
    private $mailer;

    public function __construct(
        MailerInterface $mailer,
        string $fromAddress,
        string $fromName
    ) {
        $this->mailer = $mailer;
        $this->fromAddress = $fromAddress;
        $this->fromName = $fromName;
    }

    /**
     * Send a simple email
     *
     * @param string $toEmail Recipient email
     * @param string $toName Recipient name
     * @param string $subject Email subject
     * @param string $content Text content
     * @param string|null $htmlContent Optional HTML content
     */
    public function send($toEmail, $toName, $subject, $content, $htmlContent = null)
    {
        $email = (new Email())
            ->from(sprintf('%s <%s>', $this->fromName, $this->fromAddress))
            ->to(sprintf('%s <%s>', $toName, $toEmail))
            ->subject($subject)
            ->text($content);

        if ($htmlContent) {
            $email->html($htmlContent);
        }

        $this->mailer->send($email);
    }
    
    /**
     * Send an email with attachments
     *
     * @param string $toEmail Recipient email
     * @param string $toName Recipient name
     * @param string $subject Email subject
     * @param string $content Text content
     * @param string|null $htmlContent Optional HTML content
     * @param array $attachments Array of attachments [path => string, filename => string, contentType => string]
     */
    public function sendWithAttachments($toEmail, $toName, $subject, $content, $htmlContent = null, array $attachments = [])
    {
        $email = (new Email())
            ->from(sprintf('%s <%s>', $this->fromName, $this->fromAddress))
            ->to(sprintf('%s <%s>', $toName, $toEmail))
            ->subject($subject)
            ->text($content);

        if ($htmlContent) {
            $email->html($htmlContent);
        }
        
        // Add attachments if any
        foreach ($attachments as $attachment) {
            if (isset($attachment['path'], $attachment['filename'])) {
                $contentType = $attachment['contentType'] ?? 'application/octet-stream';
                $email->attachFromPath($attachment['path'], $attachment['filename'], $contentType);
            }
        }

        $this->mailer->send($email);
    }
    
    /**
     * Send a pre-configured Email object
     * 
     * @param Email $email The email to send
     */
    public function sendEmail(Email $email)
    {
        $this->mailer->send($email);
    }
}