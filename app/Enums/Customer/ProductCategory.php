<?php

namespace App\Enums\Customer;

use App\Concerns\HasOptions;

enum ProductCategory: string
{
    use HasOptions;

    case Ppe = 'ppe';
    case Consumables = 'consumables';
    case HandTools = 'hand_tools';
    case PowerTools = 'power_tools';
    case PneumaticTools = 'pneumatic_tools';
    case MaterialHandling = 'material_handling';
    case CleaningEquipment = 'cleaning_equipment';
    case IndustrialEquipment = 'industrial_equipment';
    case IndustrialChemicals = 'industrial_chemicals';
    case MeasuringTools = 'measuring_tools';
    case MedicalHealth = 'medical_health';
    case WeldingEquipment = 'welding_equipment';
    case HomeLiving = 'home_living';
    case SafetySigns = 'safety_signs';
    case Stationery = 'stationery';
    case OfficeEquipment = 'office_equipment';
    case GeneralGoods = 'general_goods';
    case CorporateGifts = 'corporate_gifts';

    public function label(): string
    {
        return match ($this) {
            self::Ppe => 'Bảo hộ lao động',
            self::Consumables => 'Vật tư tiêu hao',
            self::HandTools => 'Dụng cụ cầm tay',
            self::PowerTools => 'Dụng cụ dùng điện',
            self::PneumaticTools => 'Dụng cụ dùng khí nén',
            self::MaterialHandling => 'Vận chuyển nâng đỡ',
            self::CleaningEquipment => 'Thiết bị làm sạch',
            self::IndustrialEquipment => 'Thiết bị công nghiệp',
            self::IndustrialChemicals => 'Hóa chất công nghiệp',
            self::MeasuringTools => 'Thiết bị, dụng cụ đo',
            self::MedicalHealth => 'Thiết bị y tế, sức khỏe',
            self::WeldingEquipment => 'Máy hàn & Phụ kiện hàn',
            self::HomeLiving => 'Nhà cửa & đời sống',
            self::SafetySigns => 'Safety Labels & Safety Signs',
            self::Stationery => 'Văn phòng phẩm',
            self::OfficeEquipment => 'Thiết bị văn phòng',
            self::GeneralGoods => 'Bách hoá tổng hợp',
            self::CorporateGifts => 'Quà tặng doanh nghiệp',
        };
    }
}
