<?php

namespace Hwkdo\MsGraphLaravel\Jobs;

use Hwkdo\IntranetAppFormwerk\Models\Typ;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphMailServiceInterface;
use Hwkdo\MsGraphLaravel\Models\GraphWebhookJobMapping;
use Hwkdo\MsGraphLaravel\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;


class ProcessFormwerkMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    

    public function __construct(public array $data)
    {
        //
    }

    public function handle()
    {
        $mailService = app(MsGraphMailServiceInterface::class);
        Log::info('ProcessFormwerkMail', $this->data);
        
        $mailResource = $this->data['resource'];
        
        $mail = $mailService->get($mailResource);
        $mailSubject = $mail->getSubject();
        $mailData = [
            'von_name' => $mail->getFrom()->getEmailAddress()->getName(),
            'von_email' => $mail->getFrom()->getEmailAddress()->getAddress(),
            'betreff' => $mailSubject,
            'has_attachments' => $mail->getHasAttachments()
        ];
        Log::info('ProcessFormwerkMail', $mailData);
        
        $found = false;
        foreach(Typ::all() as $typ)
        {
            if(str_contains($mailSubject,$typ->subject))
            {
                Log::info('ProcessFormwerkMail Betreff '.$mail->getSubject().' enthaelt '.$typ->subject);
                $identifier = str($mailSubject)->after($typ->subject)->before('##')->value();
                $pivot = $typ->webhooks()->latest()->wherePivot('identifier', $identifier)->first()->pivot;
                $pivot->update([
                    'ms_graph_mail_resource' => $mailResource
                ]);
                $jobClass = $typ->jobClass;
                $jobClass::dispatch($mailResource, $identifier);
                return;             
            }
        }  
    }
}
