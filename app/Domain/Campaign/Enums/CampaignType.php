<?php

namespace App\Domain\Campaign\Enums;

enum CampaignType: string
{
    case SchoolTour = 'school_tour';
    case OpenDay = 'open_day';
    case DigitalAds = 'digital_ads';
    case Scholarship = 'scholarship';
    case Referral = 'referral';
    case PostAdmissionCare = 'post_admission_care';

    public function label(): string
    {
        return match ($this) {
            self::SchoolTour => 'Tư vấn tại trường',
            self::OpenDay => 'Ngày hội mở',
            self::DigitalAds => 'Quảng cáo số',
            self::Scholarship => 'Học bổng',
            self::Referral => 'Giới thiệu',
            self::PostAdmissionCare => 'Chăm sóc sau trúng tuyển',
        };
    }
}
