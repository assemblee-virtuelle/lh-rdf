=== LH RDF ===
Contributors: shawfactor
Donate link: http://lhero.org/plugins/lh-rdf/
Tags: feed, feeds, rdf, localhero, sioc, skos, json, semantic web
Requires at least: 3.0
Tested up to: 4.3
Stable tag: trunk

This plugin publishes your weblog as RDF in multiple formats (xml, turtle, json etc). Mapping WordPress objects to the major ontologies.

== Description ==

It has been developed for use in [LocalHero][].

[LocalHero]: http://localhero.biz/

Once activated the plugin adds a new type of feed that can be subscribed to. E.G. http://localhero.biz/?feed=lhrdf or http://localhero.biz/feed/lhrdf/ containing semantic content.

To output the feed in other triple formats just add the get variable format= to the query string E.G. http://localhero.biz/?feed=lhrdf&format=json.

Ontologies exposed in the RDF output include:

* RDF: Resource Description Framework Ontology;
* SIOC: Semantically-Interlinked Online Communities Ontology;
* OAI-ORE Vocabulary for Resource Aggregation;
* DCTerms: Dublin Core Metadata Ontology;
* FOAF: Friend of a Friend Ontology;
* SKOS: Simple Knowledge Organization System Ontology;

The plugin also allows you to map user and post meta as rdf properties. It will also allow connect created via the plugin Posts 2 Posts to rdf properties via configurable settings.

== Installation ==

1. Upload the entire `lh-rdf` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. To enable pretty permalinks (e.g. `http://example.com/feed/lhrdf/`), go to Permalinks in the Setting menu and Save.
4. If you wish to map additional relationships install the Posts 2 Posts and add some connections then map them to rdf by going to Settings LH RDf->Mappings

== Changelog ==

**1.21 August 20, 2015**
* Show empty categories

**1.2 August 20, 2015**
* Fixed feed hook

**1.1 August 17, 2015**
* Bug fix post testing

**1.0 August 12, 2015**
* Complete code overhaul

**0.3.1 February 12, 2015**
* Minor bugfix and wp version number

**0.3.0 December 09, 2013**
* Fixed hard code title value

**0.2.9 July 30, 2013**
* Fixed wordpress pings to allow for semantic pinging

**0.2.8 July 19, 2013**
* File reorganisation and datadump

**0.2.7 June 19, 2013**
* DC Abstract support

**0.2.6 June 18, 2013**
* Various enhancements

**0.2.5 June 14, 2013**
* Thumbnail enhancements

**0.2.4 June 12, 2013**
* RDF for attachments

**0.2.3 June 11, 2013**
* Bug Fix and simplifications

**0.2.2 April 12, 2013**
* Fixed visualiser

**0.2.1 April 12, 2013**
* Added Easyrdf output parsing

**0.2.0 March 15, 2013**
* Image attachment support

**0.1.9 January 30, 2013**
* Mbox email hash

**0.1.8 January 18, 2013**
* Better page  handling

**0.1.7 January 14, 2013**
* Added Visualiser shortcode

**0.1.6 January 12, 2013**
* Fixed Auto discovery bug

**0.1.5 May 19, 2012**
* Added flag to publish extended RDF

**0.1.4 May 05, 2012**
* Added openarchives and Incontext

**0.1.3 April 22, 2012**
* Added dcterms identifier

**0.1.2 March 02, 2012**
* Fixed RDf bug, added tag feed

**0.1.1 January 05, 2012**
* Fixed critical bug

**0.1.0 December 26, 2011**
* Fixed Date Scheme

**0.0.9 October 5, 2011**
* Fixed feed permalinks

**0.0.8 September 25, 2011**
* Fixed Bugs

**0.0.7 September 22, 2011**
* Added Post thumbnail support

**0.0.6 September 19, 2011**
* Fixed SIOC topics and added SIOC terms

**0.0.5 September 19, 2011**
Added custom post type support

**0.0.4 September 19, 2011**
Added content negotiation.

**0.0.3 September 19, 2011**
Autodiscovery.

**0.0.2 September 15, 2011**
Bug fix.

**0.0.1 August 15, 2011**
Initial release.
