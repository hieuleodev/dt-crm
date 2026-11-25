<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $campaign->name }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f5f7;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;">
                    {{-- Header doanh nghiệp --}}
                    <tr>
                        <td style="background-color:#c8102e;padding:20px 24px;color:#ffffff;">
                            <div style="font-size:18px;font-weight:700;letter-spacing:0.2px;">
                                {{ $company->name ?: config('app.name') }}
                            </div>
                            <div style="font-size:12px;opacity:0.9;margin-top:4px;">
                                {{ \App\Models\BroadcastCampaign::PURPOSES[$campaign->purpose] ?? 'Thông tin từ doanh nghiệp' }}
                            </div>
                        </td>
                    </tr>

                    {{-- Lời chào --}}
                    <tr>
                        <td style="padding:20px 24px 8px 24px;font-size:14px;line-height:1.6;">
                            Kính gửi <strong>{{ $recipient->recipient_name ?: 'Quý khách hàng' }}</strong>,
                        </td>
                    </tr>

                    {{-- Bài viết chính (Hero) --}}
                    @php
                        $hero = $articles->first();
                        $secondary = $articles->slice(1);
                    @endphp

                    @if($hero)
                        <tr>
                            <td style="padding:12px 24px 20px 24px;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                                    @if($hero->cover_image_url)
                                        <tr>
                                            <td>
                                                <img src="{{ $hero->cover_image_url }}" alt="{{ $hero->title }}" style="display:block;width:100%;max-height:280px;object-fit:cover;">
                                            </td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <td style="padding:18px 20px;">
                                            <div style="font-size:18px;font-weight:700;color:#111827;line-height:1.35;">
                                                {{ $hero->title }}
                                            </div>
                                            @if($hero->summary)
                                                <div style="font-size:14px;color:#4b5563;line-height:1.6;margin-top:10px;">
                                                    {!! nl2br(e($hero->summary)) !!}
                                                </div>
                                            @endif
                                            @if($hero->cta_url)
                                                <div style="margin-top:16px;">
                                                    <a href="{{ $hero->cta_url }}" target="_blank" style="display:inline-block;background-color:#c8102e;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;padding:10px 20px;border-radius:6px;">
                                                        {{ $hero->cta_label ?: 'Xem chi tiết' }}
                                                    </a>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endif

                    {{-- Các bài viết phụ (Đa bài viết) --}}
                    @if($secondary->isNotEmpty())
                        <tr>
                            <td style="padding:0 24px 16px 24px;">
                                <div style="font-size:13px;font-weight:700;text-transform:uppercase;color:#6b7280;margin-bottom:10px;">
                                    Tin liên quan &amp; Ưu đãi khác
                                </div>
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                    @foreach($secondary as $item)
                                        <tr>
                                            <td style="padding:12px 0;border-top:1px solid #f0f0f0;">
                                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                                    <tr>
                                                        @if($item->cover_image_url)
                                                            <td width="92" valign="top" style="padding-right:12px;">
                                                                <img src="{{ $item->cover_image_url }}" alt="{{ $item->title }}" style="display:block;width:84px;height:64px;object-fit:cover;border-radius:6px;">
                                                            </td>
                                                        @endif
                                                        <td valign="top">
                                                            <div style="font-size:15px;font-weight:700;color:#111827;line-height:1.35;">
                                                                {{ $item->title }}
                                                            </div>
                                                            @if($item->summary)
                                                                <div style="font-size:13px;color:#4b5563;line-height:1.5;margin-top:4px;">
                                                                    {{ $item->summary }}
                                                                </div>
                                                            @endif
                                                            @if($item->cta_url)
                                                                <div style="margin-top:6px;">
                                                                    <a href="{{ $item->cta_url }}" target="_blank" style="color:#c8102e;font-size:13px;font-weight:600;text-decoration:none;">
                                                                        {{ $item->cta_label ?: 'Xem thêm' }} &rarr;
                                                                    </a>
                                                                </div>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    @endif

                    {{-- Footer doanh nghiệp --}}
                    <tr>
                        <td style="background-color:#f9fafb;padding:16px 24px;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;line-height:1.6;">
                            <div><strong>{{ $company->name ?: config('app.name') }}</strong></div>
                            @if($company->hotline || $company->email)
                                <div>
                                    @if($company->hotline) Hotline: {{ $company->hotline }} @endif
                                    @if($company->hotline && $company->email) &middot; @endif
                                    @if($company->email) Email: {{ $company->email }} @endif
                                </div>
                            @endif
                            @if($company->address)
                                <div>Địa chỉ: {{ $company->address }}</div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
