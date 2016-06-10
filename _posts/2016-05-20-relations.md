---
layout: page
title: "Relations"
category: schemas
date: 2016-05-20 12:11:58
order: 2
---

Individual object types are easy to setup, but when building real applications developers will often need to connect objects together and build relations.

With relational databases, such as MySQL, relations are included in the core system, and querying multiple objects through their relations is easily done through SQL with JOINS statements, sometimes at the expense of performance.

In NoSQL databases querying multiple, related objects turn out to be more painful as NoSQL do not handle JOINS.

And when it comes to querying two or more different databases simultaneously, developers usually answer: "No way".

Fortunately it's a pretty easy task to handle with Alambic.

You can define the following relations between object types:

* One to One, aka "has one"
* One to Many, aka "has many"
* EmbedsOne
* EmbedsMany

## One to One

A one to one relation between object type A and object type B defines that every instance of A "has one" instance of B. For example every "Post" has one "Author".

The declaring type (Post) has a foreign key property (authorId) that references the primary key (id) of the target type (Author).

~~~json
{
  "Post": {
    "fields": {
      "Author": {
        "type": "Author",
        "relation": {
          "id": "authorId"
        }    
      }
    }
  }
}
~~~

The relation is always built as a "key:value" expression where the key is target type key name and the value is the declaring type key name.

## One to many

A one to many relation between object type A and object type B defines that every instance of A "has many" instances of B. For example every "Author" has many "Posts".

The target type (Post) has a foreign key property that references the primary key of the declaring type (Author).

The only difference between one-to-one and one-to-many declarations is that the "multivalued" option is set to true for one-to-many.

~~~json
{
  "Author": {
    "fields": {
      "Posts": {
        "type": "Post",
        "multivalued": true,
        "relation": {
          "authorId": "id"
        }    
      }
    }
  }
}
~~~

## Embed One

## Embeds Many
