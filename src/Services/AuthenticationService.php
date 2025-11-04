<?php

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Client;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphAuthenticationServiceInterface;
use Microsoft\Graph\GraphServiceClient;

class AuthenticationService implements MsGraphAuthenticationServiceInterface
{
    protected static GraphServiceClient $graph;

    public function __construct()
    {
        $g = new Client;
        self::$graph = $g();
    }

    public function getMethods(string $upn): array
    {
        // Note: Beta API methods need special handling in v2
        // This may require using the beta GraphServiceClient
        $response = self::$graph->users()
            ->byUserId($upn)
            ->authentication()
            ->methods()
            ->get()
            ->wait();

        return $response->getValue();
    }

    public function getMethod(string $upn, string $type, string $methodId): array
    {
        return match ($type) {
            '#microsoft.graph.passwordAuthenticationMethod' => $this->getPasswordMethod($upn, $methodId),
            '#microsoft.graph.phoneAuthenticationMethod' => $this->getPhoneMethod($upn, $methodId),
            '#microsoft.graph.fido2AuthenticationMethod' => $this->getFido2Method($upn, $methodId),
            '#microsoft.graph.microsoftAuthenticatorAuthenticationMethod' => $this->getMicrosoftAuthenticatorMethod($upn, $methodId),
            '#microsoft.graph.emailAuthenticationMethod' => $this->getEmailMethod($upn, $methodId),
        };
    }

    private function getPasswordMethod(string $upn, string $methodId): array
    {
        $data = self::$graph->users()
            ->byUserId($upn)
            ->authentication()
            ->passwordMethods()
            ->byPasswordAuthenticationMethodId($methodId)
            ->get()
            ->wait();

        return [
            'id' => $data->getId(),
        ];
    }

    private function getPhoneMethod(string $upn, string $methodId): array
    {
        $data = self::$graph->users()
            ->byUserId($upn)
            ->authentication()
            ->phoneMethods()
            ->byPhoneAuthenticationMethodId($methodId)
            ->get()
            ->wait();

        return [
            'id' => $data->getId(),
            'phoneNumber' => $data->getPhoneNumber(),
            'phoneType' => $data->getPhoneType()->value(),
        ];
    }

    private function getFido2Method(string $upn, string $methodId): array
    {
        $data = self::$graph->users()
            ->byUserId($upn)
            ->authentication()
            ->fido2Methods()
            ->byFido2AuthenticationMethodId($methodId)
            ->get()
            ->wait();

        return [
            'id' => $data->getId(),
            'model' => $data->getModel(),
            'displayName' => $data->getDisplayName(),
        ];
    }

    private function getMicrosoftAuthenticatorMethod(string $upn, string $methodId): array
    {
        $data = self::$graph->users()
            ->byUserId($upn)
            ->authentication()
            ->microsoftAuthenticatorMethods()
            ->byMicrosoftAuthenticatorAuthenticationMethodId($methodId)
            ->get()
            ->wait();

        return [
            'id' => $data->getId(),
            'displayName' => $data->getDisplayName(),
            'phoneAppVersion' => $data->getPhoneAppVersion(),
            'deviceTag' => $data->getDeviceTag(),
            'device' => $data->getDevice(),
        ];
    }

    private function getEmailMethod(string $upn, string $methodId): array
    {
        $data = self::$graph->users()
            ->byUserId($upn)
            ->authentication()
            ->emailMethods()
            ->byEmailAuthenticationMethodId($methodId)
            ->get()
            ->wait();

        return [
            'id' => $data->getId(),
            'emailAddress' => $data->getEmailAddress(),
        ];
    }

    public function deleteMethod(string $upn, string $type, string $methodId): bool
    {
        return match ($type) {
            '#microsoft.graph.passwordAuthenticationMethod' => $this->deletePasswordMethod($upn, $methodId),
            '#microsoft.graph.phoneAuthenticationMethod' => $this->deletePhoneMethod($upn, $methodId),
            '#microsoft.graph.fido2AuthenticationMethod' => $this->deleteFido2Method($upn, $methodId),
            '#microsoft.graph.microsoftAuthenticatorAuthenticationMethod' => $this->deleteMicrosoftAuthenticatorMethod($upn, $methodId),
            '#microsoft.graph.emailAuthenticationMethod' => $this->deleteEmailMethod($upn, $methodId),
        };
    }

    private function deletePasswordMethod(string $upn, string $methodId): bool
    {
        // Microsoft Graph erlaubt das Löschen der Passwort-Authentifizierungsmethode nicht.
        // Stattdessen geben wir false zurück, damit Aufrufer entsprechend reagieren können.
        return false;
    }

    private function deletePhoneMethod(string $upn, string $methodId): bool
    {
        self::$graph->users()
            ->byUserId($upn)
            ->authentication()
            ->phoneMethods()
            ->byPhoneAuthenticationMethodId($methodId)
            ->delete()
            ->wait();

        return true;
    }

    private function deleteFido2Method(string $upn, string $methodId): bool
    {
        self::$graph->users()
            ->byUserId($upn)
            ->authentication()
            ->fido2Methods()
            ->byFido2AuthenticationMethodId($methodId)
            ->delete()
            ->wait();

        return true;
    }

    private function deleteMicrosoftAuthenticatorMethod(string $upn, string $methodId): bool
    {
        self::$graph->users()
            ->byUserId($upn)
            ->authentication()
            ->microsoftAuthenticatorMethods()
            ->byMicrosoftAuthenticatorAuthenticationMethodId($methodId)
            ->delete()
            ->wait();

        return true;
    }

    private function deleteEmailMethod(string $upn, string $methodId): bool
    {
        self::$graph->users()
            ->byUserId($upn)
            ->authentication()
            ->emailMethods()
            ->byEmailAuthenticationMethodId($methodId)
            ->delete()
            ->wait();

        return true;
    }
}
