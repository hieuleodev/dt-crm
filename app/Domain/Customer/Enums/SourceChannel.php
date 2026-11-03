<?php

namespace App\Domain\Customer\Enums;

use App\Domain\Shared\Concerns\HasOptions;

enum SourceChannel: string
{
    use HasOptions;

    case Seo = 'seo';
    case AiSearch = 'ai_search';
    case GoogleAds = 'google_ads';
    case SocialAds = 'social_ads';
    case ZaloOa = 'zalo_oa';
    case Facebook = 'facebook';
    case Linkedin = 'linkedin';
    case Referral = 'referral';
    case Outbound = 'outbound';
    case Tender = 'tender';
    case Event = 'event';
    case Hotline = 'hotline';
    case Marketplace = 'marketplace';
    case Existing = 'existing';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Seo => 'Google SEO (website)',
            self::AiSearch => 'AI search (ChatGPT, Gemini…)',
            self::GoogleAds => 'Google Ads',
            self::SocialAds => 'Facebook / Zalo Ads',
            self::ZaloOa => 'Zalo OA',
            self::Facebook => 'Facebook',
            self::Linkedin => 'LinkedIn',
            self::Referral => 'Khách / đối tác giới thiệu',
            self::Outbound => 'Sale tự tìm',
            self::Tender => 'Đấu thầu / eAuction',
            self::Event => 'Triển lãm / hội thảo',
            self::Hotline => 'Hotline',
            self::Marketplace => 'Sàn TMĐT',
            self::Existing => 'Khách cũ',
            self::Other => 'Khác',
        };
    }
}