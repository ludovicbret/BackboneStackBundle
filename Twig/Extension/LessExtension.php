<?php

namespace Lb\BackboneStackBundle\Twig\Extension;

use Twig_Extension;
use Twig_Filter_Method;

class LessExtension extends \Twig_Extension
{
    public function getFilters()
    {
        return array(
            'less' => new Twig_Filter_Method($this, 'lessFilter'),
        );
    }

    public function lessFilter($number, $decimals = 0, $decPoint = '.', $thousandsSep = ',')
    {
        $price = number_format($number, $decimals, $decPoint, $thousandsSep);
        $price = '$' . $price;

        return $price;
    }

    public function getName()
    {
        return 'less_extension';
    }
}
