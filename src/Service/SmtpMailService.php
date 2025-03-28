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
}