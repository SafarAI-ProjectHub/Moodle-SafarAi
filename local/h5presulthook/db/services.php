<?php
// local/h5presulthook/db/services.php

$functions = [

    // 📤 إرسال نتيجة H5P إلى Laravel
    'local_h5presulthook_send_result' => [
        'classname'   => 'local_h5presulthook\external',
        'methodname'  => 'send_result',
        'classpath'   => 'local/h5presulthook/classes/external.php',
        'description' => 'Send detailed H5P result (score, duration, xAPI, metadata) to external Laravel endpoint.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities'=> 'mod/h5pactivity:submit',
    ],

    // 🔍 جلب نتائج H5P لمحاولات المستخدم كاملة
    'local_h5presulthook_get_result' => [
        'classname'   => 'local_h5presulthook\external',
        'methodname'  => 'get_result',
        'classpath'   => 'local/h5presulthook/classes/external.php',
        'description' => 'Retrieve all H5P attempts for a user with detailed scores, durations, and xAPI data.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities'=> 'mod/h5pactivity:view',
    ],

];
