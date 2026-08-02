<?php

namespace App\Console\Commands;

use App\Mail\SpeedZoneSmtpTestMail;
use App\Services\MailDeliveryService;
use Illuminate\Console\Command;

class TestMailCommand extends Command
{
    protected $signature = 'mail:test
                            {email? : Recipient email address}
                            {--show-config : Display current mail configuration}';

    protected $description = 'Send a test email to verify SMTP / mail configuration';

    public function handle(MailDeliveryService $mail): int
    {
        if ($this->option('show-config')) {
            $this->displayConfiguration($mail);

            return self::SUCCESS;
        }

        $recipient = $this->argument('email') ?? env('MAIL_TEST_TO');

        if (! $recipient) {
            $this->error('Provide a recipient email or set MAIL_TEST_TO in .env');

            return self::FAILURE;
        }

        $this->displayConfiguration($mail);
        $this->newLine();

        if (config('mail.default') === 'log') {
            $this->warn('MAIL_MAILER is set to "log". Emails are written to storage/logs/laravel.log only — not delivered to inboxes.');
            $this->line('Set MAIL_MAILER=smtp and configure SMTP credentials in .env, then run: php artisan config:clear');
            $this->newLine();
        }

        $this->info("Sending test email to {$recipient}...");

        $sent = $mail->send($recipient, new SpeedZoneSmtpTestMail);

        if (! $sent) {
            $this->error('Test email failed. Check storage/logs/laravel.log for details.');
            $this->newLine();
            $this->line('Common fixes:');
            $this->line('  • Set MAIL_PASSWORD to your mailbox or SMTP app password');
            $this->line('  • Confirm MAIL_HOST / MAIL_PORT with your hosting provider (587+TLS or 465+SSL)');
            $this->line('  • For local debugging only: MAIL_MAILER=log (emails go to storage/logs/laravel.log)');
            $this->line('  • After .env changes: php artisan config:clear');

            return self::FAILURE;
        }

        if (config('mail.default') === 'log') {
            $this->info('Test email written to the log mailer. Check storage/logs/laravel.log.');
        } else {
            $this->info('Test email sent successfully.');
        }

        return self::SUCCESS;
    }

    private function displayConfiguration(MailDeliveryService $mail): void
    {
        $config = $mail->configurationSummary();

        $this->table(
            ['Setting', 'Value'],
            collect($config)->map(fn ($value, $key) => [
                $key,
                $value === null || $value === '' ? '(empty)' : (string) $value,
            ])->values()->all()
        );
    }
}
