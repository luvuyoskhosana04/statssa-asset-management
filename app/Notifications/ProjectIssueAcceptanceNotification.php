<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
class ProjectIssueAcceptanceNotification extends Notification
{
    public function __construct(public string $project, public string $assetTag, public string $returnDate, public int $acceptanceId) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(trans('projects.email_subject'))
            ->line(trans('projects.email_body', ['project' => $this->project, 'asset' => $this->assetTag, 'date' => $this->returnDate]))
            ->action(trans('projects.email_action'), route('account.accept.item', $this->acceptanceId))
            ->line(trans('projects.email_footer'));
    }
}
