<?php

return [
    'title'             => 'Users',
    'subtitle'          => 'System user account management',
    'new_user'          => 'New User',
    'edit_user'         => 'Edit User',
    'add_subtitle'      => 'Add a user account to the system',
    'back'              => '← Back',
    'save_user'         => 'Save User',
    'save_changes'      => 'Save Changes',
    'cancel'            => 'Cancel',
    'edit_btn'          => 'Edit',
    'no_users'          => 'No users recorded.',

    // Table columns
    'col_user'          => 'User',
    'col_email'         => 'Email',
    'col_unit'          => 'Unit',
    'col_role'          => 'Role',
    'col_status'        => 'Status',
    'col_supervisor'    => 'Supervisor',
    'col_actions'       => 'Actions',
    'active'            => 'Active',
    'inactive'          => 'Inactive',

    // Form sections
    'form_personal'     => 'Personal Information',
    'form_org'          => 'Organisational Structure',
    'form_security'     => 'Security',
    'errors_title'      => 'Please fix the following errors:',

    // Form fields
    'field_name'        => 'Full Name',
    'field_name_ph'     => 'First and last name',
    'field_email'       => 'Email',
    'field_phone'       => 'Phone',
    'field_job_title'   => 'Job Title',
    'field_unit'        => 'Unit',
    'field_unit_ph'     => '— Select unit —',
    'field_role'        => 'Role',
    'field_role_ph'     => '— Select role —',
    'field_role_none_for_unit' => 'No roles apply to this unit type. Please choose a different unit.',
    'field_confirm_unit_type_change' => 'This moves the user between headquarters and a Research Centre, which rebuilds their whole approval chain. Check this box to confirm.',
    'field_supervisor'  => 'Direct Supervisor',
    'field_supervisor_auto'=> 'Automatically assigned from the organisational reporting line.',
    'field_supervisor_empty'=> 'No active eligible supervisor is available for the selected unit and role.',
    'field_supervisor_ph'=> '— No supervisor —',
    'field_is_active'   => 'Active account',
    'field_password'    => 'Password',
    'field_password_hint'=> '(Leave blank to keep unchanged)',
    'field_password_ph' => 'New password',
    'invite_notice'     => 'An invitation email will be sent to this address with a link to set their password. No password is required now.',
    'invited_success'           => ':name has been added and an invitation email has been sent.',
    'deactivate_pending_warning'=> 'Cannot deactivate: this user has :count pending approval(s). Reassign or resolve those requests first.',
    'dg_supervisor_missing'     => 'No active Director General is configured to assign as this user\'s supervisor.',
    'director_supervisor_missing' => 'No active Director is configured for this section\'s Directorate to assign as this user\'s supervisor.',
    'centre_manager_supervisor_missing' => 'No active Centre Manager is configured for this Research Centre to assign as this user\'s supervisor.',
    'role_not_valid_for_unit'   => 'That role does not apply to the selected unit. Please choose a role that matches its type.',
    'unit_type_change_requires_confirmation' => 'Moving this user between headquarters and a Research Centre rebuilds their whole approval chain. Please confirm this change before saving.',

    // Index page
    'search_placeholder' => 'Search by name, email, or staff number…',
    'stat_total'         => 'Total',
    'stat_total_sub'     => 'Registered accounts',
    'stat_active_sub'    => 'Active accounts',
    'stat_inactive_sub'  => 'Disabled accounts',

    // Create sidebar
    'create_info_title'  => 'New User Account',
    'create_info_body'   => 'Fill in the staff details, assign the correct role and unit. A temporary password will be generated and the user will receive an invitation email.',
    'role_guide'         => 'Role Guide',

    // Edit sidebar
    'edit_note_title'    => 'Note',
    'edit_note_body'     => 'Changing a user\'s role, unit, or supervisor takes effect on their next submitted travel request.',

    // Role guide descriptions
    'role_desc_staff'            => 'Submits travel requests. No approval responsibilities.',
    'role_desc_head'             => 'Section head; reviews requests before the director.',
    'role_desc_manager'          => 'Senior staff level with limited approval scope.',
    'role_desc_supervisor'       => 'Research centre supervisor; reviews requests before the centre manager.',
    'role_desc_director'         => 'HQ directorate approver, acts before DG.',
    'role_desc_centre_manager'   => 'Final approver for research centre staff.',
    'role_desc_director_general' => 'Ultimate final approver for all HQ requests.',
    'role_desc_hr'               => 'Receives notification copies only — cannot approve.',
    'role_desc_system_admin'     => 'Manages user accounts, roles, and unit assignments.',
    'lead_post_already_held' => ':name is already the :role of :unit. A unit has one lead at a time — change their role first, then appoint the new one.',
];
