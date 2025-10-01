# Sysmex CBC Integration Guide

## Overview

This guide explains how to use the `SysmexCbcInserter` class to insert CBC (Complete Blood Count) data from ACON devices into the Sysmex table in the Jawda Medical system.

## Architecture

### Components

1. **SysmexCbcInserter**: Main class for inserting CBC data into Sysmex table
2. **ACONHandler**: HL7 message processor for ACON devices
3. **DeviceHandlerInterface**: Interface for all HL7 device handlers
4. **SysmexResult**: Eloquent model for Sysmex table

### Data Flow

```
ACON Device → HL7 Message → ACONHandler → SysmexCbcInserter → Sysmex Table
```

## SysmexCbcInserter Class

### Features

- ✅ Insert CBC data into Sysmex table
- ✅ Update existing Sysmex records
- ✅ Validate CBC data before insertion
- ✅ Map CBC parameters to Sysmex fields
- ✅ Retrieve and delete Sysmex records
- ✅ Extract CBC data from Sysmex records

### Methods

#### Core Methods

```php
// Insert new CBC data
$result = $sysmexInserter->insertCbcData($cbcResults, $doctorVisitId, $patientInfo);

// Update existing record
$result = $sysmexInserter->updateCbcData($sysmexId, $cbcResults);

// Get latest result for doctor visit
$result = $sysmexInserter->getLatestSysmexResult($doctorVisitId);

// Delete record
$result = $sysmexInserter->deleteSysmexResult($sysmexId);
```

#### Utility Methods

```php
// Validate CBC data
$validation = $sysmexInserter->validateCbcData($cbcResults);

// Get field mapping
$mapping = $sysmexInserter->getCbcToSysmexFieldMapping();

// Get Sysmex field names
$fields = $sysmexInserter->getSysmexFieldNames();

// Get CBC parameter names
$params = $sysmexInserter->getCbcParameterNames();
```

## CBC Parameter Mapping

### White Blood Cell Parameters

| CBC Parameter | Sysmex Field | Description |
|---------------|--------------|-------------|
| WBC | wbc | White Blood Cell count |
| LYM# | lym_count | Lymphocyte count |
| MXD# | mid_count | Mixed cell count |
| NEUT# | neut_count | Neutrophil count |
| LYM% | lym_percent | Lymphocyte percentage |
| MXD% | mid_percent | Mixed cell percentage |
| NEUT% | neut_percent | Neutrophil percentage |

### Red Blood Cell Parameters

| CBC Parameter | Sysmex Field | Description |
|---------------|--------------|-------------|
| RBC | rbc | Red Blood Cell count |
| HGB | hgb | Hemoglobin |
| HCT | hct | Hematocrit |
| MCV | mcv | Mean Corpuscular Volume |
| MCH | mch | Mean Corpuscular Hemoglobin |
| MCHC | mchc | Mean Corpuscular Hemoglobin Concentration |
| RDW-CV | rdw_cv | Red Cell Distribution Width (CV) |
| RDW-SD | rdw_sd | Red Cell Distribution Width (SD) |

### Platelet Parameters

| CBC Parameter | Sysmex Field | Description |
|---------------|--------------|-------------|
| PLT | plt | Platelet count |
| MPV | mpv | Mean Platelet Volume |
| PDW | pdw | Platelet Distribution Width |
| PCT | pct | Plateletcrit |
| PLCC | plcc | Platelet Large Cell Count |
| PLCR | plcr | Platelet Large Cell Ratio |

## Usage Examples

### Basic Usage

```php
use App\Services\HL7\Devices\SysmexCbcInserter;

$sysmexInserter = new SysmexCbcInserter();

// Sample CBC data from ACON device
$cbcResults = [
    'WBC' => ['value' => '7.0', 'unit' => '10*9/L', 'reference_range' => '4.0-10.0'],
    'RBC' => ['value' => '4.61', 'unit' => '10*12/L', 'reference_range' => '3.50-5.50'],
    'HGB' => ['value' => '10.7', 'unit' => 'g/dL', 'reference_range' => '11.0-16.0'],
    'PLT' => ['value' => '464', 'unit' => '10*9/L', 'reference_range' => '100-300'],
    // ... more parameters
];

$patientInfo = [
    'patient_id' => 'P123456',
    'name' => 'John Doe',
    'dob' => '1980-01-15',
    'gender' => 'M'
];

$doctorVisitId = 123;

// Insert data
$result = $sysmexInserter->insertCbcData($cbcResults, $doctorVisitId, $patientInfo);

if ($result['success']) {
    echo "Data inserted successfully. Sysmex ID: " . $result['sysmex_id'];
} else {
    echo "Error: " . $result['message'];
}
```

### Validation

```php
// Validate data before insertion
$validation = $sysmexInserter->validateCbcData($cbcResults);

if ($validation['valid']) {
    // Proceed with insertion
    $result = $sysmexInserter->insertCbcData($cbcResults, $doctorVisitId, $patientInfo);
} else {
    // Handle validation errors
    foreach ($validation['errors'] as $error) {
        echo "Validation Error: " . $error . "\n";
    }
}
```

### Retrieving Data

```php
// Get latest result for a doctor visit
$latestResult = $sysmexInserter->getLatestSysmexResult($doctorVisitId);

if ($latestResult) {
    // Extract CBC data from Sysmex record
    $cbcData = $sysmexInserter->getCbcDataFromSysmex($latestResult);
    
    foreach ($cbcData as $parameter => $data) {
        echo "{$parameter}: {$data['value']} {$data['unit']}\n";
    }
}
```

## Integration with ACONHandler

The `ACONHandler` automatically uses the `SysmexCbcInserter` when processing HL7 messages:

```php
// In ACONHandler::processMessage()
if (isset($patientInfo['doctor_visit_id'])) {
    $insertResult = $this->sysmexInserter->insertCbcData(
        $cbcResults, 
        $patientInfo['doctor_visit_id'], 
        $patientInfo
    );
    
    if (!$insertResult['success']) {
        // Handle insertion error
    }
}
```

## Database Schema

### Sysmex Table Structure

The Sysmex table should have the following fields:

```sql
CREATE TABLE sysmex (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    doctorvisit_id BIGINT,
    patient_id VARCHAR(255),
    patient_name VARCHAR(255),
    patient_dob DATE,
    patient_gender VARCHAR(10),
    
    -- White Blood Cell Parameters
    wbc DECIMAL(10,2),
    lym_count DECIMAL(10,2),
    mid_count DECIMAL(10,2),
    neut_count DECIMAL(10,2),
    lym_percent DECIMAL(10,2),
    mid_percent DECIMAL(10,2),
    neut_percent DECIMAL(10,2),
    
    -- Red Blood Cell Parameters
    rbc DECIMAL(10,2),
    hgb DECIMAL(10,2),
    hct DECIMAL(10,2),
    mcv DECIMAL(10,2),
    mch DECIMAL(10,2),
    mchc DECIMAL(10,2),
    rdw_cv DECIMAL(10,2),
    rdw_sd DECIMAL(10,2),
    
    -- Platelet Parameters
    plt DECIMAL(10,2),
    mpv DECIMAL(10,2),
    pdw DECIMAL(10,2),
    pct DECIMAL(10,2),
    plcc DECIMAL(10,2),
    plcr DECIMAL(10,2),
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_doctorvisit_id (doctorvisit_id),
    INDEX idx_patient_id (patient_id),
    INDEX idx_created_at (created_at)
);
```

## Error Handling

The class provides comprehensive error handling:

```php
$result = $sysmexInserter->insertCbcData($cbcResults, $doctorVisitId, $patientInfo);

if (!$result['success']) {
    // Handle error
    $errorMessage = $result['message'];
    $errorData = $result['data']; // null on error
    
    // Log error or show to user
    Log::error("Sysmex insertion failed: " . $errorMessage);
}
```

## Testing

### Test Script

Use the provided test script to verify functionality:

```bash
php test-sysmex-simple.php
```

### Expected Output

```
Sysmex CBC Inserter Test (Simple)
==================================

1. Testing CBC Data Validation
-------------------------------
✅ CBC data validation passed

2. Testing Field Mapping
------------------------
CBC Parameter -> Sysmex Field Mapping:
   WBC -> wbc
   LYM# -> lym_count
   MXD# -> mid_count
   ...

✅ All tests completed successfully!
```

## Best Practices

1. **Always validate data** before insertion
2. **Handle errors gracefully** with proper error messages
3. **Use transactions** for critical operations
4. **Log important operations** for debugging
5. **Test with real data** before production deployment

## Troubleshooting

### Common Issues

1. **Doctor visit not found**: Ensure the doctor visit ID exists
2. **Validation errors**: Check that all required fields are present
3. **Database connection**: Verify Laravel database configuration
4. **Field mapping**: Ensure Sysmex table has all required fields

### Debug Mode

Enable debug mode to see detailed information:

```php
// Add debug output
echo "CBC Results: " . json_encode($cbcResults, JSON_PRETTY_PRINT);
echo "Patient Info: " . json_encode($patientInfo, JSON_PRETTY_PRINT);
echo "Doctor Visit ID: " . $doctorVisitId;
```

## Support

For issues or questions:
1. Check the test scripts for examples
2. Review the error messages carefully
3. Verify database schema matches expected structure
4. Test with sample data first

---

**Created**: October 2, 2025  
**Version**: 1.0  
**Author**: Jawda Medical Development Team
