<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <meta name="supported-color-schemes" content="light">
  <title>{{ __('mail.welcome.subject') }}</title>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,800&family=DM+Sans:opsz,wght@9..40,500;9..40,700&display=swap" rel="stylesheet">
  <style>
    body { margin: 0; padding: 0; background: #f3efe7; }
    a { color: #5b46d6; }
    @media (max-width: 600px) {
      .card-pad { padding: 32px 24px !important; }
      .heading { font-size: 24px !important; }
    }
  </style>
</head>
<body style="margin:0; padding:0; background:#f3efe7;">
  {{-- Preview text shown next to the subject in the inbox. --}}
  <div style="display:none; max-height:0; overflow:hidden; opacity:0;">{{ __('mail.welcome.preheader') }}</div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3efe7;">
    <tr>
      <td align="center" style="padding:40px 16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">

          {{-- Logo --}}
          <tr>
            <td align="center" style="padding:8px 0 32px;">
              <a href="{{ $siteUrl }}" style="text-decoration:none;">
                <img src="{{ $logoUrl }}" width="64" alt="Merkis.app" style="display:block; width:64px; height:auto; border:0;">
              </a>
            </td>
          </tr>

          {{-- Card --}}
          <tr>
            <td class="card-pad" style="background:#ffffff; border-radius:30px; padding:44px 40px; font-family:'DM Sans', 'Segoe UI', Helvetica, Arial, sans-serif; color:#17171c;">
              <h1 class="heading" style="margin:0 0 16px; font-family:'Bricolage Grotesque', 'DM Sans', 'Segoe UI', Helvetica, Arial, sans-serif; font-size:30px; line-height:1.1; font-weight:800; letter-spacing:-1px; color:#17171c;">
                {{ __('mail.welcome.heading', ['name' => $name]) }}
              </h1>
              <p style="margin:0 0 32px; font-size:16px; line-height:1.6; font-weight:500; color:#6f6b62;">
                {{ __('mail.welcome.intro') }}
              </p>

              <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="border-radius:999px; background:#17171c;">
                    <a href="{{ $appUrl }}" style="display:inline-block; padding:14px 30px; font-family:'DM Sans', 'Segoe UI', Helvetica, Arial, sans-serif; font-size:15px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:999px;">
                      {{ __('mail.welcome.cta') }}
                    </a>
                  </td>
                </tr>
              </table>

              <p style="margin:36px 0 0; font-size:15px; line-height:1.6; font-weight:500; color:#6f6b62;">
                {{ __('mail.welcome.signoff') }}<br>
                <span style="color:#17171c; font-weight:700;">{{ __('mail.welcome.team') }}</span>
              </p>
            </td>
          </tr>

          {{-- Footer --}}
          <tr>
            <td align="center" style="padding:28px 16px 0; font-family:'DM Sans', 'Segoe UI', Helvetica, Arial, sans-serif; font-size:12px; line-height:1.6; font-weight:500; color:#6f6b62;">
              {!! __('mail.welcome.footer', ['site' => '<a href="'.e($siteUrl).'" style="color:#5b46d6; font-weight:700; text-decoration:none;">merkis.app</a>']) !!}
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
