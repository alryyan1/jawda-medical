# ACON Device Integration Guide

## Overview
This guide covers the integration of ACON HA-360 hematology analyzer with the HL7 message processing system. The ACON device sends CBC (Complete Blood Count) results via HL7 messages.

## ACON Handler Features

### 1. CBC Parameter Extraction
The ACON handler extracts the following CBC parameters:

#### White Blood Cell (WBC) Parameters:
- **WBC** (6690-2): White Blood Cell Count
- **LYM#** (731-0): Lymphocyte Count
- **MXD#** (8005): Mixed Cell Count  
- **NEUT#** (8006): Neutrophil Count
- **LYM%** (736-9): Lymphocyte Percentage
- **MXD%** (8007): Mixed Cell Percentage
- **NEUT%** (8008): Neutrophil Percentage

#### Red Blood Cell (RBC) Parameters:
- **RBC** (789-8): Red Blood Cell Count
- **HGB** (718-7): Hemoglobin
- **HCT** (4544-3): Hematocrit
- **MCV** (787-2): Mean Corpuscular Volume
- **MCH** (785-6): Mean Corpuscular Hemoglobin
- **MCHC** (786-4): Mean Corpuscular Hemoglobin Concentration
- **RDW-CV** (788-0): Red Cell Distribution Width CV
- **RDW-SD** (21000-5): Red Cell Distribution Width SD

#### Platelet Parameters:
- **PLT** (777-3): Platelet Count
- **MPV** (32623-1): Mean Platelet Volume
- **PDW** (32207-3): Platelet Distribution Width
- **PCT** (8002): Plateletcrit
- **PLCC** (8003): Platelet Large Cell Count
- **PLCR** (8004): Platelet Large Cell Ratio

### 2. Patient Information Extraction
- Patient ID
- Patient Name
- Date of Birth
- Gender

### 3. Database Storage
Results are stored in two tables:
- `acon_cbc_results`: Complete test results as JSON
- `acon_cbc_parameters`: Individual parameters for easy querying

### 4. ACK Message Generation
Automatic acknowledgment messages are sent back to the device.

## HL7 Message Structure

### Sample ACON Message:
```
MSH|^~\&|ACON|HA-360|389C0002C48||20250723203219||ORU^R01|1|P|2.3.1||||||UNICODE
PID|1|||| |||U
PV1|1||^|
OBR|1||5676|00001^Automated Count^ACC|||20250723195102|||||||||||||||||HM||||||||administrator
OBX|5|ST|6690-2^WBC^LN||7.0|10*9/L|4.0-10.0||||F
OBX|13|ST|718-7^HGB^LN||10.7|g/dL|11.0-16.0|↓|||F
...
```

### Message Segments:
- **MSH**: Message header with device identification
- **PID**: Patient identification
- **PV1**: Patient visit information
- **OBR**: Observation request
- **OBX**: Individual test results

## Setup Instructions

### 1. Run Database Migration
```bash
php artisan migrate
```

### 2. Test the Integration
```bash
php test-acon-parsing.php
```

### 3. Start HL7 Server
```bash
php artisan hl7:serve --port=6400
```

## Database Schema

### acon_cbc_results Table
```sql
CREATE TABLE acon_cbc_results (
    id BIGINT PRIMARY KEY,
    patient_id VARCHAR(255),
    patient_name VARCHAR(255),
    patient_dob DATE,
    patient_gender VARCHAR(255),
    device_type VARCHAR(255) DEFAULT 'ACON',
    test_date DATETIME,
    results JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### acon_cbc_parameters Table
```sql
CREATE TABLE acon_cbc_parameters (
    id BIGINT PRIMARY KEY,
    patient_id VARCHAR(255),
    parameter_name VARCHAR(255),
    test_code VARCHAR(255),
    test_name VARCHAR(255),
    value VARCHAR(255),
    unit VARCHAR(255),
    reference_range VARCHAR(255),
    abnormal_flag VARCHAR(255),
    status VARCHAR(255) DEFAULT 'F',
    test_date DATETIME,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

## Usage Examples

### Query CBC Results by Patient
```php
use Illuminate\Support\Facades\DB;

$results = DB::table('acon_cbc_results')
    ->where('patient_id', '12345')
    ->orderBy('test_date', 'desc')
    ->get();
```

### Query Specific Parameter
```php
$hgbResults = DB::table('acon_cbc_parameters')
    ->where('parameter_name', 'HGB')
    ->where('patient_id', '12345')
    ->orderBy('test_date', 'desc')
    ->get();
```

### Get Abnormal Results
```php
$abnormalResults = DB::table('acon_cbc_parameters')
    ->whereNotNull('abnormal_flag')
    ->where('abnormal_flag', '!=', '')
    ->get();
```

## Reference Ranges

The handler includes built-in reference ranges for all CBC parameters:

| Parameter | Normal Range | Unit |
|-----------|--------------|------|
| WBC | 4.0-10.0 | 10*9/L |
| RBC | 3.50-5.50 | 10*12/L |
| HGB | 11.0-16.0 | g/dL |
| HCT | 35.0-54.0 | % |
| PLT | 100-300 | 10*9/L |
| MCV | 80.0-100.0 | fL |
| MCH | 27.0-34.0 | pg |
| MCHC | 32.0-36.0 | g/dL |

## Error Handling

The ACON handler includes comprehensive error handling:
- Invalid HL7 message format
- Missing segments
- Database connection issues
- ACK message generation failures

All errors are logged with detailed information for debugging.

## Testing

### Manual Testing
1. Send HL7 message to the server
2. Check database for stored results
3. Verify ACK message is sent back

### Automated Testing
Run the test script:
```bash
php test-acon-parsing.php
```

## Troubleshooting

### Common Issues

1. **Message Not Processed**
   - Check if device identifier "ACON" is in MSH segment
   - Verify HL7 message format
   - Check server logs

2. **Database Errors**
   - Ensure migration has been run
   - Check database connection
   - Verify table permissions

3. **ACK Not Sent**
   - Check network connectivity
   - Verify device is listening for responses
   - Check server logs for errors

### Log Files
- Laravel logs: `storage/logs/laravel.log`
- HL7 server logs: Console output

## Integration with Frontend

The ACON results can be displayed in the frontend using the existing HL7 parser page or by creating a dedicated CBC results viewer.

### API Endpoints
Create API endpoints to retrieve CBC results:
```php
Route::get('/api/acon/cbc-results/{patientId}', [ACONController::class, 'getCBCResults']);
Route::get('/api/acon/abnormal-results', [ACONController::class, 'getAbnormalResults']);
```

## Security Considerations

1. **Data Validation**: All incoming HL7 data is validated
2. **SQL Injection**: Using Eloquent ORM prevents SQL injection
3. **Access Control**: Implement proper authentication for API endpoints
4. **Data Encryption**: Consider encrypting sensitive patient data

## Performance Optimization

1. **Database Indexing**: Proper indexes on frequently queried columns
2. **Batch Processing**: For high-volume scenarios
3. **Caching**: Cache reference ranges and device configurations
4. **Connection Pooling**: For database connections

## Future Enhancements

1. **Real-time Notifications**: Notify clinicians of abnormal results
2. **Trend Analysis**: Track parameter changes over time
3. **Quality Control**: Implement QC sample processing
4. **Integration**: Connect with LIS/HIS systems
5. **Reporting**: Generate automated reports
