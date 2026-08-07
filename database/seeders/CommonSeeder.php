<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Day;
use App\Models\ServiceType;
use App\Models\Type;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Doctor;
use App\Models\Lead;
use App\Models\MembershipTier;
use App\Models\TimeSlot;
use App\Models\AppointmentService;
use App\Enums\ServiceCategory;
use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Enums\SourceOfEntry;
use App\Enums\InquiryType;
use App\Enums\Gender;
use App\Enums\Relation;
use Illuminate\Database\Seeder;

class CommonSeeder extends Seeder
{
    /**
     * Run the database seeds for Audiology CRM.
     *
     * @return void
     */
    public function run(): void
    {
        // Seed Days
        $days = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        foreach ($days as $day) {
            Day::firstOrCreate(['day' => $day]);
        }

        // Seed Time Slots
        $timeSlots = [
            ['start_time' => '09:00', 'end_time' => '10:00', 'status' => 1],
            ['start_time' => '10:00', 'end_time' => '11:00', 'status' => 1],
            ['start_time' => '11:00', 'end_time' => '12:00', 'status' => 1],
            ['start_time' => '12:00', 'end_time' => '13:00', 'status' => 1],
            ['start_time' => '14:00', 'end_time' => '15:00', 'status' => 1],
            ['start_time' => '15:00', 'end_time' => '16:00', 'status' => 1],
            ['start_time' => '16:00', 'end_time' => '17:00', 'status' => 1],
        ];

        foreach ($timeSlots as $timeSlot) {
            TimeSlot::firstOrCreate([
                'start_time' => $timeSlot['start_time'],
                'end_time'   => $timeSlot['end_time'],
            ], $timeSlot);
        }

        // Seed Appointment Services
        $appointmentServices = [
            ['name' => 'Hearing Test', 'description' => 'Comprehensive hearing evaluation', 'category' => ServiceCategory::DIAGNOSTIC->value, 'max_per_day' => 10, 'status' => 1],
            ['name' => 'Tinnitus Assessment', 'description' => 'Tinnitus evaluation and management', 'category' => ServiceCategory::DIAGNOSTIC->value, 'max_per_day' => 8, 'status' => 1],
            ['name' => 'Ear Wax Removal', 'description' => 'Professional ear cleaning', 'category' => ServiceCategory::TREATMENT->value, 'max_per_day' => 15, 'status' => 1],
            ['name' => 'Hearing Aid Fitting', 'description' => 'Custom hearing aid fitting session', 'category' => ServiceCategory::TREATMENT->value, 'max_per_day' => 6, 'status' => 1],
            ['name' => 'Audiology Consultation', 'description' => 'General audiology consultation', 'category' => ServiceCategory::CONSULTATION->value, 'max_per_day' => 12, 'status' => 1],
            ['name' => 'Follow-up Visit', 'description' => 'Post-fitting follow-up check', 'category' => ServiceCategory::CONSULTATION->value, 'max_per_day' => 15, 'status' => 1],
            ['name' => 'Telehealth Consultation', 'description' => 'Remote hearing consultation', 'category' => ServiceCategory::REMOTE->value, 'max_per_day' => 20, 'status' => 1],
        ];

        foreach ($appointmentServices as $service) {
            AppointmentService::firstOrCreate(['name' => $service['name']], $service);
        }

        // Seed Membership Tiers
        $membershipTiers = [
            [
                'name' => 'Bronze',
                'min_purchase' => 0,
                'max_purchase' => 10000,
                'earn_percentage' => 1.00,
                'referral_reward_points' => 100,
            ],
            [
                'name' => 'Silver',
                'min_purchase' => 10000.01,
                'max_purchase' => 24999.99,
                'earn_percentage' => 2.00,
                'referral_reward_points' => 200,
            ],
            [
                'name' => 'Gold',
                'min_purchase' => 25000.00,
                'max_purchase' => 49999.99,
                'earn_percentage' => 3.00,
                'referral_reward_points' => 300,
            ],
            [
                'name' => 'Platinum',
                'min_purchase' => 50000.00,
                'max_purchase' => null,
                'earn_percentage' => 4.00,
                'referral_reward_points' => 400,
            ],
        ];

        foreach ($membershipTiers as $tier) {
            MembershipTier::firstOrCreate(['name' => $tier['name']], $tier);
        }

        // Seed Categories
        $categories = [
            ['category_name' => 'Behind-the-Ear (BTE)', 'status' => 1],
            ['category_name' => 'In-the-Ear (ITE)', 'status' => 1],
            ['category_name' => 'Completely-in-Canal (CIC)', 'status' => 1],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(['category_name' => $category['category_name']], $category);
        }

        // Seed Types
        $types = [
            ['type_name' => 'Digital', 'status' => 1],
            ['type_name' => 'Analog', 'status' => 1],
            ['type_name' => 'Wireless', 'status' => 1],
        ];

        foreach ($types as $type) {
            Type::firstOrCreate(['type_name' => $type['type_name']], $type);
        }

        // Seed Brands
        $brands = [
            ['brand_name' => 'Phonak', 'status' => 1],
            ['brand_name' => 'Oticon', 'status' => 1],
            ['brand_name' => 'Widex', 'status' => 1],
        ];

        foreach ($brands as $brand) {
            Brand::firstOrCreate(['brand_name' => $brand['brand_name']], $brand);
        }

        // Get IDs (existing or newly inserted)
        $categoryIds = Category::pluck('id')->toArray();
        $typeIds = Type::pluck('id')->toArray();
        $brandIds = Brand::pluck('id')->toArray();

        // Seed Products
        $products = [
            [
                'category_id' => $categoryIds[0],
                'type_id' => $typeIds[0],
                'brand_id' => $brandIds[0],
                'model' => 'Phonak Audéo B',
                'battery_size' => '312',
                'warranty_inventory_months' => 24,
                'warranty_inventory_alert_date' => now()->addMonths(18)->toDateString(),
                'warranty_sales_months' => 24,
                'warranty_sales_expiry_date' => now()->addYears(2)->toDateString(),
            ],
            [
                'category_id' => $categoryIds[1],
                'type_id' => $typeIds[0],
                'brand_id' => $brandIds[1],
                'model' => 'Oticon Opn S',
                'battery_size' => '13',
                'warranty_inventory_months' => 24,
                'warranty_inventory_alert_date' => now()->addMonths(18)->toDateString(),
                'warranty_sales_months' => 24,
                'warranty_sales_expiry_date' => now()->addYears(2)->toDateString(),
            ],
            [
                'category_id' => $categoryIds[2],
                'type_id' => $typeIds[2],
                'brand_id' => $brandIds[2],
                'model' => 'Widex Evoke',
                'battery_size' => '10',
                'warranty_inventory_months' => 24,
                'warranty_inventory_alert_date' => now()->addMonths(18)->toDateString(),
                'warranty_sales_months' => 24,
                'warranty_sales_expiry_date' => now()->addYears(2)->toDateString(),
            ],
        ];

        foreach ($products as $product) {
            Product::firstOrCreate(['model' => $product['model']], $product);
        }

        // Seed Suppliers
        $suppliers = [
            [
                'supplier_name' => 'Hearing Solutions Inc.',
                'phone' => '+1-555-0101',
                'mobile_no' => '+1-555-0102',
                'email1' => 'contact@hearingsolutions.com',
                'email2' => 'sales@hearingsolutions.com',
                'address1' => '123 Audio Street',
                'address2' => 'Suite 100',
                'fax' => '+1-555-0103',
                'state' => 'California',
                'city' => 'Los Angeles',
                'country' => 'USA',
                'zip' => '90001',
                'status' => 1,
            ],
            [
                'supplier_name' => 'Audiology Supplies Ltd.',
                'phone' => '+1-555-0201',
                'mobile_no' => '+1-555-0202',
                'email1' => 'info@audiologysupplies.com',
                'email2' => 'orders@audiologysupplies.com',
                'address1' => '456 Sound Avenue',
                'address2' => 'Building B',
                'fax' => '+1-555-0203',
                'state' => 'New York',
                'city' => 'New York City',
                'country' => 'USA',
                'zip' => '10001',
                'status' => 1,
            ],
            [
                'supplier_name' => 'Global Hearing Devices',
                'phone' => '+1-555-0301',
                'mobile_no' => '+1-555-0302',
                'email1' => 'support@globalhearing.com',
                'email2' => 'support2@globalhearing.com',
                'address1' => '789 Ear Care Road',
                'address2' => 'Unit 50',
                'fax' => '+1-555-0303',
                'state' => 'Texas',
                'city' => 'Houston',
                'country' => 'USA',
                'zip' => '77001',
                'status' => 1,
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::firstOrCreate(['supplier_name' => $supplier['supplier_name']], $supplier);
        }

        // Seed Doctors
        $doctors = [
            [
                'name' => 'Dr. Sarah Johnson',
                'contact_number' => '+1-555-1001',
                'email' => 'sarah.johnson@audiologyclinic.com',
                'address' => '101 Medical Center, Suite 201',
                'qualification' => 'Au.D. - Doctor of Audiology',
                'status' => 1,
            ],
            [
                'name' => 'Dr. Michael Chen',
                'contact_number' => '+1-555-1002',
                'email' => 'michael.chen@hearingcare.com',
                'address' => '202 Health Plaza, Floor 3',
                'qualification' => 'Ph.D. in Audiology',
                'status' => 1,
            ],
            [
                'name' => 'Dr. Emily Rodriguez',
                'contact_number' => '+1-555-1003',
                'email' => 'emily.rodriguez@earspecialist.com',
                'address' => '303 Wellness Center, Room 105',
                'qualification' => 'M.S. in Audiology',
                'status' => 1,
            ],
        ];

        foreach ($doctors as $doctor) {
            Doctor::firstOrCreate(['name' => $doctor['name']], $doctor);
        }

        // Seed ServiceTypes
        $serviceTypes = [
            [
                'service_type_name' => 'Hearing Test',
                'price' => 150.00,
                'status' => 1,
            ],
            [
                'service_type_name' => 'Ear Cleaning',
                'price' => 75.00,
                'status' => 1,
            ],
            [
                'service_type_name' => 'Hearing Aid Fitting',
                'price' => 200.00,
                'status' => 1,
            ],
        ];

        foreach ($serviceTypes as $serviceType) {
            ServiceType::firstOrCreate(['service_type_name' => $serviceType['service_type_name']], $serviceType);
        }

        // Seed Leads
        $leads = [
            [
                'lead_uid' => 'LEAD-2024-001',
                'full_name' => 'John Anderson',
                'email' => 'john.anderson@email.com',
                'district_id' => 1,
                'address' => '123 Main Street, Apt 4B',
                'age' => 65,
                'gender' => Gender::MALE->value,
                'phone_number' => '+1-555-2001',
                'contact_person_name' => 'Mary Anderson',
                'relation' => Relation::OTHER->value,
                'contact_person_phone' => '+1-555-2002',
                'inquiry_for' => InquiryType::HEARING_AID->value,
                'interest_brand_product' => 'Phonak Audéo B',
                'hearing_loss' => null,
                'approx_budget' => 2500.00,
                'ref_by' => 'Dr. Sarah Johnson',
                'notes' => 'Interested in BTE hearing aids for both ears',
                'source_of_entry' => SourceOfEntry::REFERRAL->value,
                'lead_type' => LeadType::NEW_CUSTOMER->value,
                'lead_status' => LeadStatus::NEW->value,
            ],
            [
                'lead_uid' => 'LEAD-2024-002',
                'full_name' => 'Elizabeth Taylor',
                'email' => 'elizabeth.taylor@email.com',
                'district_id' => 2,
                'address' => '456 Oak Lane',
                'age' => 58,
                'gender' => Gender::FEMALE->value,
                'phone_number' => '+1-555-2003',
                'contact_person_name' => 'Robert Taylor',
                'relation' => Relation::OTHER->value,
                'contact_person_phone' => '+1-555-2004',
                'inquiry_for' => InquiryType::HEARING_AID->value,
                'interest_brand_product' => 'Oticon Opn S',
                'hearing_loss' => null,
                'approx_budget' => 3000.00,
                'ref_by' => 'Online Search',
                'notes' => 'Looking for wireless hearing solution',
                'source_of_entry' => SourceOfEntry::WEBSITE->value,
                'lead_type' => LeadType::NEW_CUSTOMER->value,
                'lead_status' => LeadStatus::VERIFIED->value,
            ],
            [
                'lead_uid' => 'LEAD-2024-003',
                'full_name' => 'William Martinez',
                'email' => 'william.martinez@email.com',
                'district_id' => 3,
                'address' => '789 Pine Street',
                'age' => 72,
                'gender' => Gender::MALE->value,
                'phone_number' => '+1-555-2005',
                'contact_person_name' => 'Susan Martinez',
                'relation' => Relation::DAUGHTER->value,
                'contact_person_phone' => '+1-555-2006',
                'inquiry_for' => InquiryType::HEARING_TEST->value,
                'interest_brand_product' => 'Widex Evoke',
                'hearing_loss' => null,
                'approx_budget' => 2000.00,
                'ref_by' => 'Friend Referral',
                'notes' => 'Needs consultation for CIC hearing aids',
                'source_of_entry' => SourceOfEntry::REFERRAL->value,
                'lead_type' => LeadType::EXISTING_CUSTOMER->value,
                'lead_status' => LeadStatus::TELE_COUNSEL_APPOINTMENT->value,
            ],
        ];

        foreach ($leads as $lead) {
            Lead::firstOrCreate(['lead_uid' => $lead['lead_uid']], $lead);
        }

        $this->command->info('CommonSeeder completed successfully!');
        $this->command->info('Seeded data (skipped duplicates):');
        $this->command->info('   - 7 Days');
        $this->command->info('   - 7 Time Slots');
        $this->command->info('   - 7 Appointment Services');
        $this->command->info('   - 4 Membership Tiers');
        $this->command->info('   - 3 Categories');
        $this->command->info('   - 3 Types');
        $this->command->info('   - 3 Brands');
        $this->command->info('   - 3 Products');
        $this->command->info('   - 3 Suppliers');
        $this->command->info('   - 3 Doctors');
        $this->command->info('   - 3 Service Types');
        $this->command->info('   - 3 Leads');
    }
}
