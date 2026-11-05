<!DOCTYPE html>
<html lang="fr">
<body style="margin:0;padding:24px;background:#EEF2F0;font-family:'Segoe UI',Arial,sans-serif;color:#13261D;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:10px;overflow:hidden;">
    <tr><td style="background:#0A3B2A;padding:18px 24px;color:#ffffff;font-size:18px;font-weight:700;">Région Anosy</td></tr>
    <tr><td style="height:4px;background:#E9B500;font-size:0;line-height:0;">&nbsp;</td></tr>
    <tr><td style="padding:28px 24px;font-size:15px;line-height:1.6;">
        @if ($nomAgent) <p style="margin:0 0 14px;">Bonjour {{ $nomAgent }},</p> @endif
        <p style="margin:0 0 20px;">{{ $texte }}</p>
        @if ($lien)
            <p style="margin:0 0 20px;">
                <a href="{{ $lien }}" style="display:inline-block;padding:12px 22px;background:#0F6B45;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;">{{ $libelleLien ?? 'Ouvrir le lien' }}</a>
            </p>
            <p style="margin:0;font-size:12px;color:#4A5B52;">Si le bouton ne fonctionne pas, copiez cette adresse dans votre navigateur :<br>{{ $lien }}</p>
        @endif
    </td></tr>
    <tr><td style="padding:14px 24px;background:#F6F8F7;font-size:12px;color:#4A5B52;">Congés, absences et missions — Région Anosy. Message automatique, merci de ne pas répondre.</td></tr>
</table>
</body>
</html>
