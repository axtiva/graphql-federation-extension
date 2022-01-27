<?php

namespace Axtiva\FlexibleGraphql\FederationExtension\Tests;

use Axtiva\FlexibleGraphql\FederationExtension\FederationSchemaExtender;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Utils\BuildSchema;
use PHPUnit\Framework\TestCase;

class FederationSchemaExtenderFederationSchemaTest extends TestCase
{
    /**
     * @param string $sdl
     * @return void
     * @dataProvider dataProviderFederatedSchema
     */
    public function testExtendSchema(string $sdl)
    {
        $schema = BuildSchema::build(Parser::parse($sdl));
        $schemaExtended = FederationSchemaExtender::build($schema);

        $this->assertTrue($schemaExtended->getQueryType()->hasField('_entities'), '_entity not found');
        $this->assertTrue($schemaExtended->getQueryType()->hasField('_service'), '_service not found');
        $this->assertTrue($schemaExtended->hasType('_Entity'), '_Entity not found');
        $this->assertTrue($schemaExtended->hasType('_Any'), '_Any not found');
        $this->assertTrue($schemaExtended->hasType('_Service'), '_Service not found');
    }

    /**
     * @param string $sdl
     * @return void
     * @dataProvider dataProviderFederatedSchema
     */
    public function testFederation_Entity(string $sdl)
    {
        $schema = BuildSchema::build(Parser::parse($sdl));
        $schemaExtended = FederationSchemaExtender::build($schema);

        /** @var UnionType $_Entity */
        $_Entity = $schemaExtended->getType('_Entity');
        $this->assertTrue($_Entity instanceof UnionType);

        foreach ($_Entity->getTypes() as $wrappedType) {
            $this->assertContains(
                $wrappedType->name,
                [
                    'Character',
                    'Species',
                ]
            );
            $this->assertNotContains(
                $wrappedType->name,
                [
                    'Planet',
                    'Query',
                    'Date',
                    'Episode',
                ]
            );
        }
    }

    public function dataProviderFederatedSchema(): iterable
    {
        yield [<<<'SDL'
scalar _FieldSet
directive @external on OBJECT | FIELD_DEFINITION
directive @requires(fields: _FieldSet!) on FIELD_DEFINITION
directive @provides(fields: _FieldSet!) on FIELD_DEFINITION
directive @key(fields: _FieldSet!) on OBJECT | INTERFACE
directive @extends on OBJECT | INTERFACE
directive @isAuthenticated on FIELD | FIELD_DEFINITION
directive @hasRole(role: String) on FIELD | FIELD_DEFINITION
directive @pow(ex: Int!) on FIELD | FIELD_DEFINITION
directive @uppercase on FIELD | FIELD_DEFINITION

type Query {
  hero: Character
}

type Character @key(fields: "id") {
  id: ID!
  name: String
  friends: [Character]
  homeWorld: Planet
  species: Species
}

type Planet {
  name: String
  climate: String
}

type Species @key(fields: "id") {
  id: ID!
  name: String
  lifespan: Int
  origin: Planet
}

enum Episode {
  NEWHOPE
  EMPIRE
  JEDI
}

scalar Date
SDL];
    }
}