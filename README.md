# Flexible Graphql PHP Apollo Federation Extension

Library extend webonyx/graphql-php or axtiva/flexible-graphql-php functionality 
for work as subgraph service of Apollo Federation

## Setup

Install by composer

```
composer require axtiva/graphql-federation-extension
```

## Demo Federated schema

How to do look at directory [example](./example):

- How to setup apollo federated schema see at [example/extend_schema.php](./example/extend_schema.php)

Run on project root directory:
```
php -S localhost:8080 ./example/extend_schema.php
```

Now you can send http graphql requests to http://localhost:8080

 Get common graphql request 
 ```gql
 query{  
  account(id:234) {
    id
    number
    transactions {
      id
      amount
    }
  }
}
 ```

Get federated representation request
```gql
query{  
  _entities(representations: [
    {__typename: "Account", id: 123}
    {__typename: "Transaction", id: 333}
  ]) {
    __typename
    ...on Account {
      id
      number
    }
    ...on Transaction {
      id
      amount
    }
  }
}
```