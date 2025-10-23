<?php

namespace App\Service\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;

class TwigEmailHandler extends AbstractProcessingHandler
{
    private array $buffer = [];

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly \Twig\Environment $twig,
        private readonly string $fromEmail,
        private readonly string $toEmail,
        private readonly string $subject,
        int|string|Level $level = Level::Critical,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        $this->buffer[] = $record;

        // Send email immediately with all buffered records
        $this->flush();
    }

    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        try {
            $email = (new TemplatedEmail())
                ->from($this->fromEmail)
                ->to($this->toEmail)
                ->subject($this->subject)
                ->htmlTemplate('email/error_alert.html.twig')
                ->context([
                    'records' => $this->buffer,
                ]);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            // Silently fail to avoid infinite loop if mailer fails
            error_log('Failed to send error email: ' . $e->getMessage());
        } finally {
            $this->buffer = [];
        }
    }

    public function close(): void
    {
        $this->flush();
    }
}

