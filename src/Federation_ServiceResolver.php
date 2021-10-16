<?php

namespace Axtiva\FlexibleGraphql\FederationExtension;

use GraphQL\Language\Printer;
use GraphQL\Type\Definition\ResolveInfo;

class Federation_ServiceResolver
{
    public function __invoke($rootValue, $args, $context, ResolveInfo $info)
    {
        return [
            'sdl' => Printer::doPrint($info->schema->getAstNode()),
        ];
    }
}