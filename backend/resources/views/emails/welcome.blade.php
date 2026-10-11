<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="dark">
  <meta name="supported-color-schemes" content="dark">
  <title>{{ __('mail.welcome.subject') }}</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">
  <style>
    body { margin: 0; padding: 0; background: #0d1f15; }
    a { color: #4c956c; }
    @media (max-width: 600px) {
      .card-pad { padding: 32px 24px !important; }
      .heading { font-size: 24px !important; }
    }
  </style>
</head>
<body style="margin:0; padding:0; background:#0d1f15;">
  {{-- Preview text shown next to the subject in the inbox. --}}
  <div style="display:none; max-height:0; overflow:hidden; opacity:0;">{{ __('mail.welcome.preheader') }}</div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#0d1f15;">
    <tr>
      <td align="center" style="padding:40px 16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">

          {{-- Logo --}}
          <tr>
            <td align="center" style="padding:8px 0 32px;">
              <a href="{{ $siteUrl }}" style="text-decoration:none;">
                <img src="{{ $logoUrl }}" width="160" alt="Merkis.app" style="display:block; width:160px; height:auto; border:0;">
              </a>
            </td>
          </tr>

          {{-- Card --}}
          <tr>
            <td class="card-pad" style="background:#fefee3; border-radius:20px; padding:44px 40px; font-family:Montserrat, 'Segoe UI', Helvetica, Arial, sans-serif; color:#143823;">
              <h1 class="heading" style="margin:0 0 16px; font-size:28px; line-height:1.2; font-weight:800; letter-spacing:-0.5px; color:#143823;">
                {{ __('mail.welcome.heading', ['name' => $name]) }}
              </h1>
              <p style="margin:0 0 32px; font-size:16px; line-height:1.6; font-weight:600; color:#4a6555;">
                {{ __('mail.welcome.intro') }}
              </p>

              <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                  <td style="border-radius:999px; background:#2c6e49;">
                    <a href="{{ $appUrl }}" style="display:inline-block; padding:14px 30px; font-family:Montserrat, 'Segoe UI', Helvetica, Arial, sans-serif; font-size:15px; font-weight:700; color:#fefee3; text-decoration:none; border-radius:999px;">
                      {{ __('mail.welcome.cta') }}
                    </a>
                  </td>
                </tr>
              </table>

              <p style="margin:36px 0 0; font-size:15px; line-height:1.6; font-weight:600; color:#4a6555;">
                {{ __('mail.welcome.signoff') }}<br>
                <span style="color:#143823; font-weight:800;">{{ __('mail.welcome.team') }}</span>
              </p>
            </td>
          </tr>

          {{-- Footer --}}
          <tr>
            <td align="center" style="padding:28px 16px 0; font-family:Montserrat, 'Segoe UI', Helvetica, Arial, sans-serif; font-size:12px; line-height:1.6; font-weight:600; color:#8fa596;">
              {!! __('mail.welcome.footer', ['site' => '<a href="'.e($siteUrl).'" style="color:#ffc9b9; font-weight:700; text-decoration:none;">merkis.app</a>']) !!}
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
