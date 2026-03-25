<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AnalyzeCourseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $courseId;

    public function __construct($courseId)
    {
        $this->courseId = $courseId;
    }

    public function handle(): void
    {
        Artisan::call('curriculum:analyze-course', [
            'course_id' => $this->courseId
        ]);
    }
}
