<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Models\User; 

class SendWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userEmail;
    protected $userName;

    public function __construct(string $email, string $name)
    {
        $this->userEmail = $email;
        $this->userName = $name;
    }

    public function handle(): void
    {
        \Log::info("QUEUE JOB: Gửi email chào mừng tới: " . $this->userName);
    }
}