<?php

namespace Tests\Unit\Engine;

use App\Engine\SchemaValidator;
use App\Models\ServiceDefinition;
use PHPUnit\Framework\TestCase;

/**
 * SchemaValidatorTest
 *
 * §11.3 Criterion 2: Automated tests for validation engine.
 * Tests verify EDA-10 Correctable Defect behavior.
 * BUILD CONTRACT P-1: Tests CONFIRM validation is enforced, never bypassed.
 */
class SchemaValidatorTest extends TestCase
{
    private function makeService(array $fields, array $documents = []): ServiceDefinition
    {
        $service = new ServiceDefinition();
        $service->setRawAttributes(['schema' => json_encode([
            'fields'    => $fields,
            'documents' => $documents,
        ])]);
        $service->syncOriginal();
        return $service;
    }

        public function test_required_field_missing_returns_error(): void
    {
        $service   = $this->makeService([
            ['id' => 'name', 'label_ar' => 'الاسم', 'label_en' => 'Name', 'type' => 'text', 'required' => true],
        ]);
        $validator = new SchemaValidator($service);

        $errors = $validator->validateData([]);

        $this->assertNotNull($errors);
        $this->assertArrayHasKey('name', $errors);
    }

        public function test_all_required_fields_present_returns_null(): void
    {
        $service   = $this->makeService([
            ['id' => 'name', 'label_ar' => 'الاسم', 'label_en' => 'Name', 'type' => 'text', 'required' => true],
        ]);
        $validator = new SchemaValidator($service);

        $errors = $validator->validateData(['name' => 'أحمد محمد']);

        $this->assertNull($errors);
    }

        public function test_pattern_validation_enforced_on_national_id(): void
    {
        $service = $this->makeService([
            [
                'id'        => 'national_id',
                'label_ar'  => 'رقم الهوية',
                'label_en'  => 'National ID',
                'type'      => 'text',
                'required'  => true,
                'pattern'   => '^[0-9]{10}$',
                'min_length' => 10,
                'max_length' => 10,
            ],
        ]);
        $validator = new SchemaValidator($service);

        // Invalid: only 5 digits
        $errors = $validator->validateData(['national_id' => '12345']);
        $this->assertNotNull($errors, 'Pattern validation must reject short national IDs');
        $this->assertArrayHasKey('national_id', $errors);

        // Valid: exactly 10 digits
        $errors = $validator->validateData(['national_id' => '1234567890']);
        $this->assertNull($errors, 'Valid national ID must pass pattern validation');
    }

        public function test_pattern_validation_is_never_skipped(): void
    {
        // BUILD CONTRACT P-1: This test ensures validation is not removed
        $service = $this->makeService([
            [
                'id'       => 'phone',
                'label_ar' => 'الهاتف',
                'label_en' => 'Phone',
                'type'     => 'text',
                'required' => true,
                'pattern'  => '^07[0-9]{8}$',
            ],
        ]);
        $validator = new SchemaValidator($service);

        $invalid = ['phone' => '123'];
        $errors  = $validator->validateData($invalid);

        $this->assertNotNull($errors, 'Pattern validation must never be skipped');
    }

        public function test_conditional_field_skipped_when_condition_not_met(): void
    {
        $service = $this->makeService([
            ['id' => 'type',   'label_ar' => 'النوع', 'label_en' => 'Type', 'type' => 'select', 'required' => true, 'options' => [['value' => 'food', 'label_ar' => 'طعام', 'label_en' => 'Food'], ['value' => 'retail', 'label_ar' => 'تجزئة', 'label_en' => 'Retail']]],
            ['id' => 'health_cert', 'label_ar' => 'شهادة صحية', 'label_en' => 'Health Cert', 'type' => 'text', 'required' => true, 'conditional' => ['field' => 'type', 'value' => 'food']],
        ]);
        $validator = new SchemaValidator($service);

        // type=retail — health_cert condition not met, so it should NOT be required
        $errors = $validator->validateData(['type' => 'retail']);
        $this->assertNull($errors, 'Conditional field must not be required when condition is not met');
    }

        public function test_required_document_missing_returns_error(): void
    {
        $service = $this->makeService([], [
            ['id' => 'id_copy', 'label_ar' => 'صورة الهوية', 'label_en' => 'ID Copy', 'required' => true],
        ]);
        $validator = new SchemaValidator($service);

        $errors = $validator->validateDocuments([]);
        $this->assertNotNull($errors);
        $this->assertArrayHasKey('id_copy', $errors);
    }

        public function test_all_required_documents_present_returns_null(): void
    {
        $service = $this->makeService([], [
            ['id' => 'id_copy', 'label_ar' => 'صورة الهوية', 'label_en' => 'ID Copy', 'required' => true],
        ]);
        $validator = new SchemaValidator($service);

        $errors = $validator->validateDocuments(['id_copy']);
        $this->assertNull($errors);
    }
}
