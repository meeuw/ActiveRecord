================
PHP ActiveRecord
================

*******
Summary
*******

This git repository is a tribute to ActiveRecord which was written by Leender Brouwer, Matthijs Tempels and Richard Wolterink. ActiveRecord was created in 2005 and the last online release I've found was in 2007.

You shouldn't use this source in any new project, use Doctrine, Propel or Eloquent instead.

ActiveRecord is a (simple) ORM for MySQL only, the primary/foreign keys are required to have the same fieldname. All data read / written to the database are strings.

*****
Usage
*****

Initialize database
===================

| ``CREATE TABLE person (``
|   ``id_person int AUTO_INCREMENT,``
|   ``id_city int,``
|   ``PRIMARY KEY (id_person)``
| ``);``

Create Model
============
| ``class Person extends ActiveRecord {``
|     ``function initTable() {``
|         ``$this->table = "person";``
|     ``}``
| ``}``

Insert Record
=============
| ``$person = new Person();``
| ``$person->insert();``
