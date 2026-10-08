<?php
declare(strict_types=1);
$root=$argv[1]??'';
if(!is_dir($root)||is_link($root)){exit(2);}
$config="[req]\ndistinguished_name=dn\nx509_extensions=ext\n[dn]\n[ext]\nsubjectAltName=IP:127.0.0.1\nbasicConstraints=critical,CA:TRUE\nkeyUsage=critical,digitalSignature,keyEncipherment,keyCertSign\nextendedKeyUsage=serverAuth\n";
file_put_contents($root.'/openssl.cnf',$config);
$options=['config'=>$root.'/openssl.cnf','private_key_bits'=>2048,'digest_alg'=>'sha256'];
$key=openssl_pkey_new($options);$csr=openssl_csr_new(['commonName'=>'EVAL local development'],$key,$options);$cert=openssl_csr_sign($csr,null,$key,30,$options);
if(!$key||!$csr||!$cert||!openssl_x509_export($cert,$pem)||!openssl_pkey_export($key,$private,null,$options)){exit(2);}
file_put_contents($root.'/cert.pem',$pem);file_put_contents($root.'/key.pem',$private);echo "Local certificate created.\n";
