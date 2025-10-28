<?php

namespace Hwkdo\MsGraphLaravel\Http\Controllers;

use App\Http\Controllers\Controller;
use Hwkdo\MsGraphLaravel\Models\GraphWebhookJobMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function __invoke(Request $request, string $typ)
    {
        Log::info('Ms-Graph-Laravel Subscription', $request->all());

        if ($request->has('validationToken')) {
            Log::info('Ms-Graph-Laravel Subscription has validationToken');

            // wenn der request ein Feld validationToken hat, dann ist es kein webhook sondern eine
            // validierungsanfrage siehe https://learn.microsoft.com/en-us/graph/change-notifications-delivery-webhooks?tabs=http#notificationurl-validation
            return response(urldecode($request->validationToken),
                200,
                ['Content-Type' => 'text/plain']
            );

        } else {
            Log::info('Ms-Graph-Laravel Subscription NO validationToken');
            if ($request->has('value')) {
                // sollte  ein echter hook von microsoft sein wenn im request ein value feld ist
                $data = $request->value;
                if ($data[0]['clientState'] !== config('ms-graph-laravel.subscription_secret')) {
                    // das Secret sollte bei jedem Webhook mitgeschickt werden.
                    // Wenn das Secret nicht uebereinstimmt, ist da was faul
                    return abort(404);
                } else {
                    // secret ist okay
                    // mail kann bearbeitet werden
                    Log::info('Ms-Graph-Laravel Subscription Typ '.$typ);

                    $jobClass = GraphWebhookJobMapping::getJobClassForType($typ);

                    if ($jobClass === null) {
                        Log::warning('Ms-Graph-Laravel: Kein Job-Mapping für Typ gefunden: '.$typ);
                        Log::warning('Ms-Graph-Laravel: Data: ', $data[0]);

                        return abort(404);
                    }

                    if (! class_exists($jobClass)) {
                        Log::error('Ms-Graph-Laravel: Job-Klasse existiert nicht: '.$jobClass);

                        return abort(500);
                    }

                    // Bestimme die Daten, die an den Job übergeben werden sollen
                    // OneDrive Jobs benötigen das gesamte Datenarray, andere nur die Resource
                    $jobData = str_contains($typ, 'onedrive')
                        ? $data[0]
                        : $data[0]['resource'];

                    // Dispatche den Job dynamisch
                    $jobClass::dispatch($jobData);

                    Log::info('Ms-Graph-Laravel: Job dispatched', [
                        'typ' => $typ,
                        'job_class' => $jobClass,
                    ]);

                    return response('', 200);
                }
            }
        }
    }
}
