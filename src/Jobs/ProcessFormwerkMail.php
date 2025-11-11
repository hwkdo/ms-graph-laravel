<?php

namespace Hwkdo\MsGraphLaravel\Jobs;

use Hwkdo\IntranetAppFormwerk\Models\Typ;
use Hwkdo\IntranetAppFormwerk\Models\TypHasWebhook;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphMailServiceInterface;
use Hwkdo\MsGraphLaravel\Models\GraphWebhookJobMapping;
use Hwkdo\MsGraphLaravel\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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
        
                  
            $uuid = str($mailSubject)->between('##', '##')->value();
            if (Validator::make(['value' => $uuid], ['value' => 'uuid'])->passes()) {
                Log::info('ProcessFormwerkMail Betreff '.$mailSubject.' hat eine UUID');                
                $pivot = TypHasWebhook::latest()->where('formwerk_uuid', $uuid)->first();
                if(!$pivot) {
                    Log::error('ProcessFormwerkMail Webhook nicht gefunden für UUID: '.$uuid);
                    return ;
                }
                $pivot->update([
                    'ms_graph_mail_resource' => $mailResource
                ]);
                $identifier = $pivot->identifier;
            }                                    
            $jobClass = $pivot->typ->jobClass;
            $jobClass::dispatch($mailResource, $identifier, $uuid);
            return;             
        
          
    }
}
