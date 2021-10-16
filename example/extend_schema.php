<?php

use Axtiva\FlexibleGraphql\FederationExtension\Federation_ServiceResolver;
use GraphQL\Error\DebugFlag;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use Axtiva\FlexibleGraphql\FederationExtension\FederationSchemaExtender;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Utils\BuildSchema;

// execute in shell command on project root dir: composer install
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    echo 'Autoloader did not generated, pls run command `composer install` on project root dir'; die;
}

// Describe demo schema
$schemaGQL = <<<GQL
# federation directives
scalar _FieldSet
directive @external on OBJECT | FIELD_DEFINITION
directive @requires(fields: _FieldSet!) on FIELD_DEFINITION
directive @provides(fields: _FieldSet!) on FIELD_DEFINITION
directive @key(fields: _FieldSet!) on OBJECT | INTERFACE
directive @extends on OBJECT | INTERFACE

type Query {
    account(id: ID!): Account
}

type Account @key(fields: "id") {
    id: ID!
    number: String!
    transactions: [Transaction!]!
}

type Transaction @key(fields: "id") {
    id: ID!
    amount: Int!
}

scalar DateTime
GQL;

/**
 * Define our types as objects
 */

class Account
{
    public $id;
    public $number;
    public function __construct($id, $number)
    {
        $this->id = $id;
        $this->number = $number;
    }
}

class Transaction
{
    public $id;
    public $amount;
    public function __construct($id, $amount)
    {
        $this->id = $id;
        $this->amount = $amount;
    }
}

/**
 * Build federated schema based on native webonix/graphql-php schema
 */

// Build webonix/graphql-php schema
$nativeSchema = BuildSchema::build($schemaGQL);

// Extend with apollo federation entities
$apolloSchema = FederationSchemaExtender::build($nativeSchema);

// Define _Entity Union type resolver
/** @var UnionType $entityType */
$entityType = $apolloSchema->getType('_Entity');

/**
 * Set union resolveType function
 * Look at resolve type definition in webonyx/graphql-php doc page
 * @link https://webonyx.github.io/graphql-php/type-definitions/unions/
 */
$entityType->config['resolveType'] = function ($value, $context, ResolveInfo $info) {
    if ($value instanceof Account) {
        return $info->schema->getType('Account');
    } elseif ($value instanceof Transaction) {
        return $info->schema->getType('Transaction');
    }
    throw new Exception('Type did not defined for this value ' . print_r($value, true));
};

/**
 * Define our resolvers for schema
 * @link https://webonyx.github.io/graphql-php/data-fetching/
 */
$queryType = $apolloSchema->getQueryType();
$queryType->getField('_service')->resolveFn = fn($value, $args, $context, ResolveInfo $info) => (new Federation_ServiceResolver())($value, $args, $context, $info);
$queryType->getField('account')->resolveFn = function ($value, $args, $context, ResolveInfo $info) {
    return new Account($args['id'], '343253GDSGS3254');
};


/**
 * Define resolver for Account.transactions field
 * @var ObjectType $accountType
 */
$accountType = $apolloSchema->getType('Account');
$accountType->getField('transactions')->resolveFn = function ($value, $args, $context, ResolveInfo $info) {
    return [new Transaction(22, 33), new Transaction(24, 53),];
};

/**
 * Define federated entity representation resolvers for each _Entity union types
 * @link https://www.apollographql.com/docs/federation/federation-spec/#resolve-requests-for-entities
 * Prepare resolvers for each _Entity types for call representation requests
 */
$representations = [
    'Account' => fn($representation, $args, $context, ResolveInfo $info) => new Account($representation['id'], 'demo'),
    'Transaction' => fn($representation, $args, $context, ResolveInfo $info) => new Transaction($representation['id'], 777),
];

// Add resolver based on $representations
$queryType->getField('_entities')->resolveFn = function (
    $rootValue,
    $args,
    $context,
    ResolveInfo $info
) use ($representations) {
    $result = [];
    foreach ($args['representations'] as $representation) {
        $typeName = $representation['__typename'];
        $type = $info->schema->getType($typeName);
        if (false === $type instanceof ObjectType) {
            throw new Exception(
                "The _entities resolver tried to load an entity for type \"${$typeName}\", but no object type of that name was found in the schema"
            );
        }
        if (empty($representations[$typeName])) {
            throw new Exception('Representation did not defined for type ' . $typeName);
        }
        $resolver = $representations[$typeName];
        $result[] = $resolver($representation, $args, $context, $info);
    }

    return $result;
};

/**
 * Setup webonix/graphql-php server
 */
$debugFlag = DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE | DebugFlag::RETHROW_INTERNAL_EXCEPTIONS | DebugFlag::RETHROW_UNSAFE_EXCEPTIONS;
$config = ServerConfig::create()
    ->setSchema($apolloSchema)
    ->setDebugFlag($debugFlag)
;
$server = new StandardServer($config);
$server->handleRequest();
