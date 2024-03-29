#! /usr/bin/env php
<?php

/*
2018 - 2022, mortzu <mortzu@uni-bremen.de>.
All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are
permitted provided that the following conditions are met:

* Redistributions of source code must retain the above copyright notice, this list of
  conditions and the following disclaimer.

* Redistributions in binary form must reproduce the above copyright notice, this list
  of conditions and the following disclaimer in the documentation and/or other materials
  provided with the distribution.

* The names of its contributors may not be used to endorse or promote products derived
  from this software without specific prior written permission.

* Feel free to send Club Mate to support the work.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS
OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS
AND CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.
*/

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/config.defaults.php';
require_once __DIR__ . '/config.php';

$nodes_list = array();

if (FALSE === $meshviewer_json = file_get_contents($config['meshviewer_json_url'])) {
    error_log('Could not fetch meshviewer.json');
    exit(1);
} elseif (empty($meshviewer_json)) {
    error_log('Fetched meshviewer.json is empty');
    exit(1);
} else {
    $meshviewer_data = json_decode($meshviewer_json, true);

    if (!is_array($meshviewer_data)) {
        error_log('Could not fetch json');
        exit(1);
    }
}

foreach ($meshviewer_data['nodes'] as $nodes)
    array_push($nodes_list, strtolower(preg_replace('/[^A-Za-z0-9-]/', '-', $nodes['hostname'])));

foreach (glob(__DIR__ . '/data/verified/*') as $node_file) {
    $node_status_current = 0;
    $node_name = basename($node_file);

    if (!in_array($node_name, $nodes_list)) {
        error_log('Moved ' . $node_name . ' to deleted');
        rename($node_file, __DIR__ . '/data/deleted/' . basename($node_name));
        continue;
    }

    $json_decoded = json_decode(file_get_contents($node_file), true);

    if (!is_array($json_decoded))
        continue;

    $email_to = $json_decoded['mail'];
    $node_status_last = (isset($json_decoded['status']) ? $json_decoded['status'] : 0);

    foreach ($meshviewer_data['nodes'] as $nodes) {
        if (($nodes['hostname'] == $node_name) && ($nodes['is_online'] == true)) {
            $node_status_current = 1;
            break;
        }
    }

    if ($node_status_current != 1) {
        if ($node_status_last == 0) {
            $mail = new PHPMailer;
            $mail->isSendmail();
            $mail->CharSet = 'utf-8';
            $mail->setFrom($config['email_from']);
            $mail->addAddress($email_to);
            $mail->isHTML(false);
            $mail->Subject = str_replace('___NODENAME___', $node_name, $config['email_subject_offline']);
            $mail->Body = str_replace(array('___NODENAME___', '___EMAIL___', '___LINK_DELETE___'), array($node_name, $email_to, $config['url_base'] . '?delete&token=' . $json_decoded['token']), $config['email_message_offline']);
            $mail->send();

            $node_status_current = 2;
        } elseif ($node_status_last == 2)
            $node_status_current = 2;
        else
            $node_status_current = 0;
    }

    $json_decoded['status'] = $node_status_current;

    file_put_contents($node_file, json_encode($json_decoded));
}
