<?php

declare(strict_types=1);

namespace Axtiva\FlexibleGraphql\FederationExtension;

use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaExtender;
use function in_array;

final class FederationSchemaExtender
{
    public static function build(Schema $schema): Schema
    {
        $entityTypeNamesList = [];
        foreach ($schema->getTypeMap() as $type) {
            if ($type instanceof ObjectType && $type->astNode !== null) {
                foreach ($type->astNode->directives as $node) {
                    if ($node->name->kind === 'Name' && $node->name->value === 'key') {
                        $entityTypeNamesList[] = $type->name;
                        break;
                    }
                }
            }
        }

        if (empty($entityTypeNamesList)) {
            return $schema;
        }

        $entityTypeNames = implode(' | ', $entityTypeNamesList);
        $queryDefinition = in_array('Query', array_keys($schema->getTypeMap()))
            ? 'extend type Query @extends'
            : 'type Query @extends';
        $unionEntitiesType = "union _Entity = $entityTypeNames";
        $sdl = <<< GRAPHQL
$unionEntitiesType
scalar _Any
type _Service {
  sdl: String!
}
$queryDefinition {
  _entities(representations: [_Any!]!): [_Entity]!
  _service: _Service!
}
GRAPHQL;

        $documentAST = Parser::parse($sdl);
        $apolloSchema = SchemaExtender::extend($schema, $documentAST);
        /** @var ObjectType $query */
        $query = $apolloSchema->getType('Query');
        if ($query->getField('_service')->resolveFn === null) {
            $query->getField('_service')->resolveFn = new Federation_ServiceResolver();
        }

        return $apolloSchema;
    }
}