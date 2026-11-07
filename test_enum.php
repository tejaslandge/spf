<?php

enum InterestMethod: string
{
    case Flat = 'flat';
    case Reducing = 'reducing';
}

echo "Trying InterestMethod::from('flat'): \n";
try {
    $m = InterestMethod::from('flat');
    echo 'Success: '.$m->value."\n";
} catch (Error $e) {
    echo 'Error: '.$e->getMessage()."\n";
}

echo "Trying InterestMethod::cases(): \n";
print_r(InterestMethod::cases());

echo "Trying to find by value: \n";
foreach (InterestMethod::cases() as $method) {
    echo '  '.$method->value." == 'flat': ".($method->value === 'flat' ? 'true' : 'false')."\n";
}
