<?php
/*
Plugin Name: LH RDF
Plugin URI: http://lhero.org/plugins/lh-rdf/
Description: Adds a semantic/SIOC RDF feed to Wordpress
Author: Peter Shaw
Version: 1.21
Author URI: http://shawfactor.com/

== Changelog ==

= 1.0 =
*Complete code overhaul

= 1.1 =
*Bug fix after testing

= 1.2 =
*Fixed feed

= 1.21 =
*Show empty categories




License:
Released under the GPL license
http://www.gnu.org/copyleft/gpl.html

Copyright 2011  Peter Shaw  (email : pete@localhero.biz)


This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published bythe Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

include_once('library/EasyRdf.php');
include_once('library/object-handlers.php');
include_once('library/php-json-ld.php');
include_once('library/relationships.php');

class LH_rdf_plugin {

  /**
   * Map the document format asked and the associated content type header
   **/
  var $format_mapper = array (
    "jsonld" => "application/ld+json",
    "json" => "application/json",
    "n3" => "text/n3",
    "ntriples" => "application/n-triples",
    "php" => "application/x-httpd-php-source",
    "rdfxml"  => "application/rdf+xml",
    "turtle" => "text/turtle",
    //"dot" => "text/vnd.graphviz",
    //"gif" => "image/gif",
    //"png" => "image/png",
    //"svg" => "image/svg+xml"
  );

  var $standard_namespaces;

  /**
  * Returns the combined list of the plugin based semantic namespaces and the
  * user defined ones.
  *
  **/
  public function return_namespaces(){
    $namespaces  = array (
      "lh"  => "http://localhero.biz/namespace/lhero/",
      "sioc" => "http://rdfs.org/sioc/ns#",
      "dc" => "http://purl.org/dc/elements/1.1/",
      "content" => "http://purl.org/rss/1.0/modules/content/",
      "dcterms" => "http://purl.org/dc/terms/",
      "admin" => "http://webns.net/mvcb/",
      "skos" => "http://www.w3.org/2004/02/skos/core#",
      "sioct" => "http://rdfs.org/sioc/types#",
      "bio" => "http://purl.org/vocab/bio/0.1/",
      "img" => "http://jibbering.com/2002/3/svg/#",
      "ore" => "http://www.openarchives.org/ore/terms/",
      "void" => "http://rdfs.org/ns/void#",
    );

    $namespaces = apply_filters( "lh_rdf_namespaces", $namespaces);
    return $namespaces;
  }

  /**
   * Get the link associated with current format
   **/
  private function get_link($format) {
    global $post;
    if ( is_singular( 'post' ) || is_single()){
      $base_mid = add_query_arg( "feed", "lhrdf", get_permalink() );
      $base_mid = add_query_arg( "format", $format, $base_mid );
    } elseif (is_author()){
      $base_mid = add_query_arg( "feed", "lhrdf", get_author_posts_url($post->post_author) );
      $base_mid = add_query_arg( "format", $format, $base_mid );
    } else {
      $base_mid = add_query_arg( "feed", "lhrdf", "http://$_SERVER[HTTP_HOST]/" );
      $base_mid = add_query_arg( "format", $format, $base_mid );
    }
    return $base_mid;
  }

  /**
   * Set the proper content type for the current request based on format query string argument
   **/
  public function map_mime_request() {
    if (isset($_REQUEST['format'])) {
      $format = preg_replace("/[^\w\-]+/", '', strtolower($_REQUEST['format']));
    } else {
      $format = 'rdfxml';
    }

    if ($this->format_mapper[$format]){
      nocache_headers();
      header('Content-Type: ' . $this->format_mapper[$format] . '; charset=' . get_option('blog_charset'), true);
    } else {
      nocache_headers();
      header('Content-Type: application/rdf+xml; charset=' . get_option('blog_charset'), true);
    }

    return $format;
  }

  /**
   * Check is the current request is asking for RDF content
   **/
  function is_rdf_request() {
  	if ( $_SERVER['HTTP_ACCEPT'] == 'application/rdf+xml' ) {
  		return true;
  	}
  	return false;
  }

  /**
   * Compute the RDF Feed URL for the current page
   **/
  function get_rdf_link() {
    global $post;
    if ( is_singular() ){
      $base_mid = get_permalink()."?feed=lhrdf";
    } elseif (is_author()){
      $base_mid = get_author_posts_url($post->post_author);
      $base_mid .= "?feed=lhrdf";
    } else {
      $base_mid = "http://$_SERVER[HTTP_HOST]";
      $base_mid .= "/";
      $base_mid .= "?feed=lhrdf";
    }
    return $base_mid;
  }


  /**
   * THE FUNCTION: Generates the Feed in the proper format based on the request
   **/
  public function do_feed_rdf() {
    // Get the current format the client is asking for
    $format = $this->map_mime_request();

    foreach ($this->return_namespaces() as $key => $value){
      EasyRdf_Namespace::set($key, $value);
    }

    $graph = new EasyRdf_Graph();
    $lh_rdf_object_handlers = new LH_rdf_object_handlers($format);

    if ($theobject = get_queried_object()){
      $thetype = get_class($theobject);
      $action = "do_content_".$thetype;
      $graph = $lh_rdf_object_handlers->$action($graph,$theobject);
    } elseif ( is_attachment() ) {
      global $wp_query;

      if ($wp_query->query[attachment_id]){
        $args=array( 'post__in' => array($wp_query->query[attachment_id]) , 'post_type' => 'attachment' );
      } else {
        $args=array(
        	'name'           => $wp_query->query[attachment],
        	'post_type'      => 'attachment',
        	'posts_per_page' => 1
        );
      }
      $attachment_post = get_posts($args);
      $graph = $lh_rdf_object_handlers->do_content_attachment($graph,$attachment_post[0]);
    } else {
      global $wp_query;
      $graph = $lh_rdf_object_handlers->do_content_wp_query($graph,$wp_query);
    }

    $graph = apply_filters( "lh_rdf_graph", $graph);
    $serialize = $graph->serialise($format);
    $etag = md5($serialize);
    header("Etag: ".$etag);
    echo $serialize;
  }

  /**
   * Add the link to all the semantic common languages in the head for the current object
   **/
  function add_link_to_head() {
    echo "\n\n<!-- Start LH RDF -->\n";
    foreach ($this->format_mapper as $key => $value){
      echo "<link rel=\"meta\" type=\"".$value."\" title=\"SIOC\"  href=\"".$this->get_link($key)."\" />\n";
    }
    echo "<!-- End LH RDF -->\n\n";
  }

  /**
   * Check if the format the request is asking for is a semantic one and is supported
   **/
  private function check_if_rdf_request() {
    $return = false;
    foreach ($this->format_mapper as $key => $value){
      if ( $_SERVER['HTTP_ACCEPT'] == $value ) {
        $return = $key;
      }
    }

    return $return;
  }

  function get_control() {
    if (!is_feed()){
      if ( $format = $this->check_if_rdf_request() ) {
        $redir = $this->get_link($format);
        if ( !empty($redir) ) {
          @header( "Location: " .  $redir );
          die();
        }
      }
    }
  }

  public static function query_var($vars) {
    $vars[] = '__datadump';
    return $vars;
  }

  public static function parse_query($wp) {
    if (!array_key_exists('__datadump', $wp->query_vars)) {
      return;
    }

    //$format = $this->map_mime_request();
    foreach ($this->standard_namespaces as $key => $value){
      EasyRdf_Namespace::set($key, $value);
    }
    EasyRdf_Namespace::delete("rss");
    $graph = new EasyRdf_Graph();
    //$lh_rdf_object_handlers = new LH_rdf_object_handlers($format);
    die;
  }

  public function init() {
    add_feed('lhrdf', array($this, 'do_feed_rdf'));
  }


  public function __construct() {
    add_action('init', array($this, 'init'));
    add_action('template_redirect', array($this, 'get_control'));
    add_action('wp_head', array($this, 'add_link_to_head'));
    add_filter('query_vars', array($this, 'query_var'));
    add_action('parse_query', array($this, 'parse_query'));
  }
}

$lh_rdf = new LH_rdf_plugin();
?>
