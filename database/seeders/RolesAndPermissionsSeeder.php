<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User; 

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $guardName = 'web'; // Use your API guard name, typically 'sanctum' or 'api'

        // --- Helper to create permissions ---
        $createPermission = function ($name) use ($guardName) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guardName]);
        };

        // --- Dashboard & General ---
        $createPermission('view dashboard');
        $createPermission('view dashboard_summary');

        // --- User Management ---
        $createPermission('list users');
        $createPermission('view users');
        $createPermission('create users');
        $createPermission('edit users');
        $createPermission('delete users');
        $createPermission('assign roles');
        $createPermission('update user_passwords');
        $createPermission('view user_shift_income');

        // --- Role & Permission Management ---
        $createPermission('list roles');
        $createPermission('view roles');
        $createPermission('create roles');
        $createPermission('edit roles');
        $createPermission('delete roles');
        $createPermission('assign permissions_to_role');
        $createPermission('list permissions');

        // --- Doctor & Specialist Management ---
        $createPermission('list doctors');
        $createPermission('create doctors');
        $createPermission('edit doctors');
        $createPermission('delete doctors');
        $createPermission('list specialists');
        $createPermission('create specialists');
        // ... (edit, delete specialists if needed)

        // --- Patient Management ---
        $createPermission('list patients'); // General list view
        $createPermission('view patients');   // Detailed patient view
        // $createPermission('create patients'); // Old general one, replaced
        $createPermission('register cash_patient');    // NEW
        $createPermission('register insurance_patient'); // NEW
        $createPermission('edit patients');   // Edit demographic/static info
        $createPermission('delete patients');
        $createPermission('search existing_patients');
        $createPermission('create_visit_from_patient_history'); // For storeVisitFromHistory
        $createPermission('copy_patient_to_new_visit'); // For createCopiedVisitForNewShift


        // --- Clinic Workspace & Visit Management ---
        $createPermission('access clinic_workspace');
        $createPermission('view active_clinic_patients');
        // create doctor_visits is covered by patient registration permissions usually
        $createPermission('reassign doctor_visits_to_shift');
        $createPermission('view doctor_visits');
        $createPermission('edit doctor_visits');
        $createPermission('update doctor_visit_status');
        // $createPermission('delete doctor_visits'); // Usually 'cancel doctor_visits'
        $createPermission('cancel doctor_visits');
        $createPermission('view patient_visit_history');

        // --- Services within a Visit ---
        $createPermission('request visit_services');
        $createPermission('edit visit_requested_service_details');
        $createPermission('remove visit_services');
        $createPermission('record visit_service_payment');
        $createPermission('manage requested_service_costs');
        $createPermission('manage service_payments_deposits'); // For the new dialog

        // --- Lab Requests within a Visit (Clinic side) ---
        $createPermission('request lab_tests_for_visit');
        $createPermission('edit lab_request_details_clinic');
        $createPermission('cancel lab_requests_clinic');
        $createPermission('clear_pending_lab_requests_for_visit');
        $createPermission('record lab_request_payment_clinic');
        $createPermission('record_batch lab_payment');


        // --- Shift Management ---
        $createPermission('view current_open_clinic_shift');
        // $createPermission('open clinic_shifts');
        // $createPermission('close clinic_shifts');
        $createPermission('manage clinic_shift_financials');
        $createPermission('list clinic_shifts');
        $createPermission('view clinic_shifts');
        $createPermission('view clinic_shift_summary'); // For dialog

        $createPermission('view active_doctor_shifts');
        $createPermission('manage doctor_shifts'); // General for start/end all
        $createPermission('start doctor_shifts'); // Could be self or for others
        $createPermission('end doctor_shifts');   // Could be self or for others
        $createPermission('list all_doctor_shifts');
        $createPermission('view doctor_shift_financial_summary');
        // $createPermission('edit doctor_shift_details'); // If applicable

        // --- Doctor Schedule & Appointments ---
        $createPermission('view doctor_schedules');
        $createPermission('manage own_doctor_schedule');
        $createPermission('manage all_doctor_schedules');
        $createPermission('list appointments');
        $createPermission('create appointments');
        $createPermission('view appointment_details');
        $createPermission('cancel appointments');
        $createPermission('update appointment_status');

        // --- Service & Service Group Definitions ---
        $createPermission('list services');
        $createPermission('create services');
        $createPermission('edit services');
        $createPermission('delete services');
        $createPermission('list service_groups');
        $createPermission('create service_groups');
        $createPermission('manage service_costs_definitions'); // For ServiceCost templates

        // --- Company & Contract Management ---
        $createPermission('list companies');
        $createPermission('create companies');
        $createPermission('edit companies');
        $createPermission('delete companies');
        $createPermission('view companies'); // Show individual company
        $createPermission('view company_contracts'); // General view for both service/test contracts linked to a company
        $createPermission('manage company_service_contracts');
        $createPermission('import_all_services_to_company_contract');
        $createPermission('copy_company_service_contracts');
        $createPermission('manage company_main_test_contracts');
        $createPermission('import_all_main_tests_to_company_contract');
        // $createPermission('manage subcompanies'); // If there's a dedicated UI for this beyond quick-add
        // $createPermission('manage company_relations');

        // --- Lab Test Definitions (Settings Area) ---
        $createPermission('list lab_tests');
        $createPermission('create lab_tests');
        $createPermission('edit lab_tests');
        $createPermission('delete lab_tests');
        $createPermission('batch_update lab_test_prices');
        $createPermission('batch_delete lab_tests');
        $createPermission('manage lab_test_parameters');
        $createPermission('manage lab_test_containers');
        $createPermission('manage lab_test_units');
        $createPermission('manage lab_test_child_groups');
        $createPermission('manage lab_test_child_options');
        $createPermission('list lab_test_packages');
        $createPermission('create lab_test_packages');
        $createPermission('edit lab_test_packages');
        $createPermission('delete lab_test_packages');
        $createPermission('view lab_price_list');
        $createPermission('print lab_price_list');
        $createPermission('manage sub_service_cost_types'); // For SubServiceCost CRUD
        // add this permission to role
        $createPermission('assign permissions to role'); // For the new service payment deposits dialog

        // --- Lab Workstation & Results ---
        $createPermission('access lab_workstation');
        $createPermission('view lab_pending_queue');
        $createPermission('edit lab_request_flags_lab'); // Sample ID, hidden, valid, no_sample from lab side
        $createPermission('enter lab_results');
        $createPermission('edit_own_lab_results'); // If techs can only edit their own unauth results
        $createPermission('edit_any_lab_results'); // For supervisors
        $createPermission('authorize lab_results');
        $createPermission('print lab_sample_labels');
        $createPermission('print lab_worklist');
        $createPermission('print lab_patient_report');
        // $createPermission('manage lab_quality_control');
        // $createPermission('sync_with_lis');

        // --- Clinical Management ---
        $createPermission('manage visit_vitals');
        $createPermission('manage visit_clinical_notes');
        $createPermission('view visit_lab_results');
        $createPermission('manage visit_prescriptions');

        // --- Financials & Costs ---
        $createPermission('list finance_accounts');
        $createPermission('record clinic_costs');
        $createPermission('list clinic_costs');
        $createPermission('print cost_report');


        // --- Reports ---
        $createPermission('view reports_section');
        $createPermission('view doctor_shift_reports');
        $createPermission('print doctor_shift_reports'); // PDF for this one
        $createPermission('view service_statistics_report');
        // $createPermission('print service_statistics_report');
        $createPermission('view monthly_lab_income_report');
        // $createPermission('print monthly_lab_income_report');
        $createPermission('view monthly_service_income_report');
        $createPermission('export monthly_service_income_pdf');
        $createPermission('export monthly_service_income_excel');
        $createPermission('print company_service_contract_report');
        $createPermission('print company_main_test_contract_report');
        $createPermission('print thermal_receipt');

        // --- Settings ---
        $createPermission('view settings');
        $createPermission('update settings');

        // --- Insurance Auditing ---
        $createPermission('list auditable_visits');
        $createPermission('view audit_record');
        $createPermission('create_or_update audit_record_patient_info');
        $createPermission('manage_audited_services');
        $createPermission('copy_original_services_to_audit');
        $createPermission('finalize_audit_record'); // verify, needs_correction, reject
        $createPermission('export_audit_claims_pdf');
        $createPermission('export_audit_claims_excel');
        
        // --- Communication ---
        $createPermission('send whatsapp_messages');
        // $createPermission('manage whatsapp_templates');
        //open financials shift
        $createPermission('open financials_shift');
        //close financials shift
        $createPermission('close financials_shift');

        // Define Roles
        $this->command->info('Creating roles...');
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => $guardName]);
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => $guardName]);
        $doctorRole = Role::firstOrCreate(['name' => 'Doctor', 'guard_name' => $guardName]);
        $receptionistRole = Role::firstOrCreate(['name' => 'Receptionist', 'guard_name' => $guardName]);
        $labTechnicianRole = Role::firstOrCreate(['name' => 'Lab Technician', 'guard_name' => $guardName]);
        $auditorRole = Role::firstOrCreate(['name' => 'Auditor', 'guard_name' => $guardName]);
        $nurseRole = Role::firstOrCreate(['name' => 'Nurse', 'guard_name' => $guardName]);
        // ... other roles

        // Assign All Permissions to Super Admin
        $allPermissions = Permission::where('guard_name', $guardName)->get();
        $superAdminRole->givePermissionTo($allPermissions);
        $this->command->info('Super Admin granted all permissions.');

        // Admin Role
        $adminRole->syncPermissions([
            'list users', 'view users', 'create users', 'edit users', 'delete users', 'assign roles',
            'list roles', 'view roles', 'create roles', 'edit roles', 'delete roles', 'assign permissions_to_role', 'list permissions',
            'list doctors', 'create doctors', 'edit doctors', 'delete doctors',
            'list patients', 'view patients', 'edit patients', 'delete patients', 'register cash_patient', 'register insurance_patient',
            'list companies', 'create companies', 'edit companies', 'delete companies', 'view companies',
            'manage company_service_contracts', 'import_all_services_to_company_contract', 'copy_company_service_contracts',
            'manage company_main_test_contracts', 'import_all_main_tests_to_company_contract',
            'view settings', 'update settings',
'manage clinic_shift_financials', 'list clinic_shifts', 'view clinic_shifts',
            'manage doctor_shifts', 'list all_doctor_shifts', 'view doctor_shift_financial_summary',
            'manage all_doctor_schedules',
            'list services', 'create services', 'edit services', 'delete services',
            'list service_groups', 'create service_groups', 'manage service_costs_definitions',
            'list lab_tests', 'create lab_tests', 'edit lab_tests', 'delete lab_tests', 'batch_update lab_test_prices', 'batch_delete lab_tests',
            'manage lab_test_parameters', 'manage lab_test_containers', 'manage lab_test_units', 'manage lab_test_child_groups', 'manage lab_test_child_options',
            'list lab_test_packages', 'create lab_test_packages', 'edit lab_test_packages', 'delete lab_test_packages',
            'view lab_price_list', 'print lab_price_list',
            'record clinic_costs', 'list clinic_costs', 'print cost_report',
            'view reports_section', // All reports implicitly
            'list auditable_visits', 'view audit_record', 'create_or_update audit_record_patient_info', 'manage_audited_services', 'copy_original_services_to_audit', 'finalize_audit_record', 'export_audit_claims_pdf', 'export_audit_claims_excel',
            'send whatsapp_messages',
            'manage sub_service_cost_types', // For the new costing system
            'manage service_payments_deposits', // For the new service payment deposits dialog
            'open financials_shift', 'close financials_shift' // For the new financials shift
        ]);
        $this->command->info('Admin role permissions set.');

        // Receptionist Role (Focus on patient flow, appointments, basic payments)
        $receptionistRole->syncPermissions([
            'view dashboard_summary',
            'search existing_patients', 'register cash_patient', 'register insurance_patient', 'view patients',
            'create_visit_from_patient_history', 'copy_patient_to_new_visit',
            'access clinic_workspace', 'view active_clinic_patients', 
            'update doctor_visit_status', // e.g., to 'waiting' or 'payment_pending'
            'view doctor_schedules', 'create appointments', 'list appointments', 'view appointment_details', 'cancel appointments', 'update appointment_status',
            'request visit_services', 'record visit_service_payment', 'manage service_payments_deposits',
            'request lab_tests_for_visit', 'record lab_request_payment_clinic', 'record_batch lab_payment',
            'view current_open_clinic_shift',
            'view active_doctor_shifts',
            'print thermal_receipt',
            'send whatsapp_messages',
        ]);
        $this->command->info('Receptionist role permissions set.');
        
        // Doctor Role
        $doctorRole->syncPermissions([
            'view dashboard_summary',
            'view patients', // View patient details they are seeing
            'access clinic_workspace', // To see their queue
            'view active_doctor_shifts', // See their own and others for context
            'start doctor_shifts', 'end doctor_shifts', // Manage their own shift
            'view doctor_shift_financial_summary', // For their own shifts
            'edit doctor_visits', // Their own visits: clinical notes, vitals
            'update doctor_visit_status', // For their patients: e.g. to with_doctor, completed
            'request visit_services', 'request lab_tests_for_visit', // Can request during consultation
            'manage visit_vitals', 'manage visit_clinical_notes', 'view visit_lab_results', 'manage visit_prescriptions',
            'manage own_doctor_schedule',
            'view reports_section', 'view doctor_shift_reports', // View reports related to their activity
        ]);
        $this->command->info('Doctor role permissions set.');

        // Lab Technician Role
        $labTechnicianRole->syncPermissions([
            'access lab_workstation', 'view lab_pending_queue',
            'edit lab_request_flags_lab', // update sample ID, valid, no_sample etc.
            'enter lab_results', 'edit_own_lab_results', // Or 'edit_any_lab_results' if more senior
            // 'authorize lab_results', // Typically a senior lab tech or pathologist
            'print lab_sample_labels', 'print lab_worklist',
            'view lab_price_list',
        ]);
        $this->command->info('Lab Technician role permissions set.');

        // Auditor Role
        $auditorRole->syncPermissions([
            'list auditable_visits', 'view audit_record', 
            'create_or_update audit_record_patient_info',
            'manage_audited_services', 
            'copy_original_services_to_audit',
            'finalize_audit_record',
            'export_audit_claims_pdf', 'export_audit_claims_excel',
            'view companies', 'list companies', // To understand company context for claims
            'view company_contracts', // To understand contract terms
            'view patients', // To view original patient data for comparison
            'view doctor_visits', // To view original visit data
        ]);
        $this->command->info('Auditor role permissions set.');
        
        // Nurse Role
        $nurseRole->syncPermissions([
            'access clinic_workspace', // To see patients they might assist
            'view active_clinic_patients',
            'update doctor_visit_status', // e.g., moving patient to lab, from waiting to triage
            'manage visit_vitals',
            // 'manage visit_clinical_notes', // If nurses take some notes
            'view doctor_schedules', // To know doctor availability
        ]);
        $this->command->info('Nurse role permissions set.');


        // You can assign a role to an existing user like this:
        // $user = User::find(1); // Find user with ID 1
        // if ($user) {
        //     $user->assignRole('Super Admin');
        //     $this->command->info('Super Admin role assigned to user ID 1.');
        // }
    }
}