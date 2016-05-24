---
layout: default
title: "Alambic"
---

# Dead easy data access

Based on Facebook [GraphQL](http://graphql.org), the Alambic project aims to provide to PHP developers a powerful data API with:

 * elegant, hierarchical, declarative [data models](http://webtales.github.io/alambic/models/introduction)
 * simple and unified query language, handling complex [relations](http://webtales.github.io/alambic/models/relations)
 * single endpoint API, dispatched to heterogeneous [data sources](http://webtales.github.io/alambic/models/data-sources)
 * built-in, extensible [middlewares](http://webtales.github.io/logic/middlewares) to add application logic

Alambic is framework agnostic, so it will play nice with your preferred PHP framework/library: Laravel, Symfony, Zend Framework...

## Core Concepts

SQL, NoSQL, hierarchical, search indexes, besides pro's and con's of each db technology, the web is evolving as a composite aggregation of various data sources. Each web page or mobile application needs to request a growing number of heterogeneous data sources, leading to multiple ad-hoc endpoints or custom libraries implementations.

Client-driven queries eliminates the need to handle separately each data source, delegating efficient and cross dbs data fetching to the Alambic server.

The core system relies on a declarative and strong-types data model, which describes the types of objects that can be returned and the relations between them.

Here is a type object description:

### Type example

```json
{
  "data": {
    "hero": {
      "name": "R2-D2"
    }
  }
}
```
