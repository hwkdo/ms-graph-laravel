<?php

namespace Hwkdo\MsGraphLaravel\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

class OutOfOfficeTemplateService
{
    public function getTemplate(User $user, User $colleague, ?Carbon $limit = null, ?string $notice = null): string
    {
        if ($limit) {
            $template = <<<EOD
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns:m="http://schemas.microsoft.com/office/2004/12/om ▶
            /* Font Definitions */\n
            @font-face\n
            \t{font-family:"Cambria Math";\n
            \tpanose-1:2 4 5 3 5 4 6 3 2 4;}\n
            @font-face\n
            \t{font-family:Calibri;\n
            \tpanose-1:2 15 5 2 2 2 4 3 2 4;}\n
            /* Style Definitions */\n
            p.MsoNormal, li.MsoNormal, div.MsoNormal\n
            \t{margin:0cm;\n
            \tfont-size:11.0pt;\n
            \tfont-family:"Calibri",sans-serif;\n
            \tmso-fareast-language:EN-US;}\n
            .MsoChpDefault\n
            \t{mso-style-type:export-only;\n
            \tfont-family:"Calibri",sans-serif;\n
            \tmso-fareast-language:EN-US;}\n
            @page WordSection1\n
            \t{size:612.0pt 792.0pt;\n
            \tmargin:70.85pt 70.85pt 2.0cm 70.85pt;}\n
            div.WordSection1\n
            \t{page:WordSection1;}\n
            --></style></head><body lang=DE link="#0563C1" vlink="#954F72" style='word-wrap:break-word'><div class=WordSection1><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'>Guten Tag,<o:p></o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'><o:p>&nbsp;</o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'>herzlichen Dank für Ihre E-Mail.<o:p></o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'><o:p>&nbsp;</o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'>Ab dem &lt;DATUM&gt; bin ich wieder für Sie persönlich zu erreichen. Ihre E-Mail wird aus datenschutzrechtlichen Gründen nicht automatisch weitergeleitet.<o:p></o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'><o:p>&nbsp;</o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'>In dringenden Fällen wenden Sie sich bitte mit Ihrem Anliegen bis zu diesem Zeitpunkt an meinen Kollegen*in:<o:p></o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'>&lt;VORNAME&gt; &lt;NACHNAME&gt;<o:p></o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'>&lt;TELEFON&gt;<o:p></o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'>&lt;EMAIL&gt;<o:p></o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'><o:p>&nbsp;</o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'>Mit freundlichen Grüßen<o:p></o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'>&lt;MEIN_VORNAME&gt; &lt;MEIN_NACHNAME&gt;<o:p></o:p></span></p></div></body></html>
            EOD;
        } else {
            $template = <<<EOD
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns:m="http://schemas.microsoft.com/office/2004/12/omml" xmlns="http://www.w3.org/TR/REC-html40"><head><meta http-equiv=Content-Type content="text/html; charset=unicode"><meta name=Generator content="Microsoft Word 15 (filtered medium)"><style><!--\n ◀
            /* Font Definitions */\n
            @font-face\n
            \t{font-family:"Cambria Math";\n
            \tpanose-1:2 4 5 3 5 4 6 3 2 4;}\n
            @font-face\n
            \t{font-family:Calibri;\n
            \tpanose-1:2 15 5 2 2 2 4 3 2 4;}\n
            @font-face\n
            \t{font-family:"Segoe UI";\n
            \tpanose-1:2 11 5 2 4 2 4 2 2 3;}\n
            /* Style Definitions */\n
            p.MsoNormal, li.MsoNormal, div.MsoNormal\n
            \t{margin:0cm;\n
            \tfont-size:11.0pt;\n
            \tfont-family:"Calibri",sans-serif;\n
            \tmso-fareast-language:EN-US;}\n
            .MsoChpDefault\n
            \t{mso-style-type:export-only;\n
            \tfont-family:"Calibri",sans-serif;\n
            \tmso-fareast-language:EN-US;}\n
            @page WordSection1\n
            \t{size:612.0pt 792.0pt;\n
            \tmargin:70.85pt 70.85pt 2.0cm 70.85pt;}\n
            div.WordSection1\n
            \t{page:WordSection1;}\n
            --></style></head><body lang=DE link="#0563C1" vlink="#954F72" style='word-wrap:break-word'><div class=WordSection1><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'>Guten Tag,<o:p></o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'><o:p>&nbsp;</o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'>herzlichen Dank für Ihre E-Mail.<o:p></o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'><o:p>&nbsp;</o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'>Zur Zeit bin ich leider nicht erreichbar. Ihre E-Mail wird aus datenschutzrechtlichen Gründen nicht automatisch weitergeleitet.<o:p></o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'><o:p>&nbsp;</o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'>In dringenden Fällen wenden Sie sich bitte mit Ihrem Anliegen an meinen Kollegen*in:<o:p></o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'>&lt;VORNAME&gt; &lt;NACHNAME&gt;<o:p></o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'>&lt;TELEFON&gt;<o:p></o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'>&lt;EMAIL&gt;<o:p></o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'><o:p>&nbsp;</o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'>Mit freundlichen Grüßen<o:p></o:p></span></p><p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:"Arial",sans-serif'>&lt;MEIN_VORNAME&gt; &lt;MEIN_NACHNAME&gt;</span><span style='font-size:8.5pt;font-family:"Segoe UI",sans-serif'><o:p></o:p></span></p></div></body></html>
            EOD;
        }

        if ($limit) {
            $template = str_replace('&lt;DATUM&gt;', $limit->format('d.m.Y'), $template);
            $template = str_replace('&lt;VORNAME&gt;', $colleague->vorname, $template);
            $template = str_replace('&lt;NACHNAME&gt;', $colleague->nachname, $template);
            $template = str_replace('&lt;TELEFON&gt;', $colleague->telefon, $template);
            $template = str_replace('&lt;EMAIL&gt;', $colleague->email, $template);
            $template = str_replace('&lt;MEIN_VORNAME&gt;', $user->vorname, $template);
            $template = str_replace('&lt;MEIN_NACHNAME&gt;', $user->nachname, $template);
        } else {
            $template = str_replace('&lt;VORNAME&gt;', $colleague->vorname, $template);
            $template = str_replace('&lt;NACHNAME&gt;', $colleague->nachname, $template);
            $template = str_replace('&lt;TELEFON&gt;', $colleague->telefon, $template);
            $template = str_replace('&lt;EMAIL&gt;', $colleague->email, $template);
            $template = str_replace('&lt;MEIN_VORNAME&gt;', $user->vorname, $template);
            $template = str_replace('&lt;MEIN_NACHNAME&gt;', $user->nachname, $template);
        }

        if ($notice !== null && trim($notice) !== '') {
            $escapedNotice = htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $greetingMarker = "<p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:\"Arial\",sans-serif'>Mit freundlichen Grüßen";
            $noticeParagraph = "<p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:\"Arial\",sans-serif'>{$escapedNotice}<o:p></o:p></span></p>";
            $emptyParagraph = "<p class=MsoNormal style='text-autospace:none'><span style='font-size:10.0pt;font-family:\"Arial\",sans-serif'><o:p>&nbsp;</o:p></span></p>";
            $template = str_replace($greetingMarker, $noticeParagraph.$emptyParagraph.$greetingMarker, $template);
        }

        return $template;
    }
}
