<?php

return [
    'sex' => ['male' => 'Male', 'female' => 'Female', 'unknown' => 'Unknown'],
    'multiplicity' => ['single' => 'Single', 'twin' => 'Twin', 'triplet' => 'Triplet', 'higher' => 'Higher multiple', 'unknown' => 'Unknown'],
    'delivery_mode' => ['vaginal' => 'Normal vaginal delivery', 'cesarean' => 'Cesarean section', 'other' => 'Other'],
    'respiratory_support' => ['none' => 'None', 'o2' => 'O₂', 'cpap' => 'CPAP', 'o2_cpap' => 'O₂ + CPAP', 'ventilator' => 'Ventilator'],
    'patient_status' => ['active' => 'Active follow-up', 'discharged' => 'Discharged', 'referred' => 'Referred', 'lost' => 'Lost to follow-up', 'deceased' => 'Deceased'],
    'zone' => ['zone_1' => 'Zone I', 'posterior_zone_2' => 'Posterior zone II', 'zone_2' => 'Zone II', 'zone_3' => 'Zone III', 'not_applicable' => 'Not applicable', 'not_assessable' => 'Not assessable'],
    'stage' => ['stage_0' => 'Stage 0', 'stage_1' => 'Stage 1', 'stage_2' => 'Stage 2', 'stage_3' => 'Stage 3', 'stage_4a' => 'Stage 4A', 'stage_4b' => 'Stage 4B', 'stage_5' => 'Stage 5', 'not_applicable' => 'Not applicable', 'not_assessable' => 'Not assessable'],
    'rop_status' => ['no_rop' => 'No ROP', 'present' => 'ROP present', 'regressing' => 'Regressing ROP', 'regressed' => 'Regressed ROP', 'fully_vascularized' => 'Fully vascularized', 'incomplete_vascularization' => 'Incomplete vascularization', 'not_assessable' => 'Not assessable'],
    'plus_disease' => ['none' => 'No plus', 'pre_plus' => 'Pre-plus', 'plus' => 'Plus disease', 'not_assessable' => 'Not assessable'],
    'rop_type' => ['type_1' => 'Type 1', 'type_2' => 'Type 2'],
    'management_plan' => ['observe' => 'Observe and follow-up', 'eylea' => 'Eylea injection', 'laser' => 'Laser', 'eylea_laser' => 'Eylea injection + Laser', 'referred' => 'Referred to Baghdad', 'discharge' => 'Discharge from ROP follow-up', 'other' => 'Other'],
    'visit_kind' => ['examination' => 'Examination', 'undated_examination' => 'Undated examination', 'treatment_only' => 'Treatment only', 'note_only' => 'Plan / note only', 'uncertain' => 'Attribution uncertain', 'index_pending' => 'Index entry (linkage pending)'],
    'treatment_type' => ['eylea' => 'Eylea injection', 'other_anti_vegf' => 'Other anti-VEGF injection', 'laser' => 'Laser', 'surgery' => 'Surgery', 'other' => 'Other'],
    'eye_side' => ['right' => 'Right eye', 'left' => 'Left eye', 'both' => 'Both eyes'],
];
