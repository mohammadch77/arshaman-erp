<?php

namespace App\Modules\HR\Enums;

enum EmployeePosition: string
{
    case HoldingCeo = 'holding_ceo';
    case FinanceManager = 'finance_manager';
    case AccountantStaff = 'accountant_staff';
    case HrManager = 'hr_manager';
    case HrStaff = 'hr_staff';
    case AdminStaff = 'admin_staff';
    case Developer = 'developer';
    case GraphicDesigner = 'graphic_designer';
    case UiUxDesigner = 'ui_ux_designer';
    case DigitalMarketer = 'digital_marketer';
    case ContentCreator = 'content_creator';
    case ProductManager = 'product_manager';
    case SalesRepresentative = 'sales_representative';
    case WarehouseStaff = 'warehouse_staff';
    case LogisticsCoordinator = 'logistics_coordinator';
    case CustomerSupport = 'customer_support';
    case ProcurementStaff = 'procurement_staff';
    case ProjectManager = 'project_manager';
    case BusinessAnalyst = 'business_analyst';
    case Consultant = 'consultant';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::HoldingCeo => 'مدیرعامل هلدینگ',
            self::FinanceManager => 'مدیر مالی',
            self::AccountantStaff => 'کارشناس حسابداری',
            self::HrManager => 'مدیر منابع انسانی',
            self::HrStaff => 'کارشناس منابع انسانی',
            self::AdminStaff => 'کارشناس اداری',
            self::Developer => 'برنامه‌نویس',
            self::GraphicDesigner => 'طراح گرافیک',
            self::UiUxDesigner => 'طراح UI/UX',
            self::DigitalMarketer => 'کارشناس بازاریابی دیجیتال',
            self::ContentCreator => 'تولیدکننده محتوا',
            self::ProductManager => 'مدیر محصول',
            self::SalesRepresentative => 'کارشناس فروش',
            self::WarehouseStaff => 'کارشناس انبار',
            self::LogisticsCoordinator => 'هماهنگ‌کننده لجستیک',
            self::CustomerSupport => 'پشتیبانی مشتریان',
            self::ProcurementStaff => 'کارشناس تدارکات',
            self::ProjectManager => 'مدیر پروژه',
            self::BusinessAnalyst => 'تحلیل‌گر کسب‌وکار',
            self::Consultant => 'مشاور',
            self::Other => 'سایر',
        };
    }
}
