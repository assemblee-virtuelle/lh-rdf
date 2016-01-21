<?php

/**
 * Class handling the RDF graph generation depending upon the requested object type
 **/
class LH_rdf_object_handlers {
  /**
   * The format to generate
   **/
  var $format;

  /**
   * Get the URI associated with the user whose id is given as parameter
   **/
  private function return_user_uri($id) {
    return get_author_posts_url($id)."#account";
  }

  /**
   * Return a resource associated with the current object requested
   **/
  private function return_seeAlso_resource($resource) {
    $resource = add_query_arg('feed','lhrdf', $resource);
    $resource = add_query_arg('format',$this->format, $resource);
    return $resource;
  }

  /**
   * Compute the current page URL based on the $SERVER variables
   **/
  private function curPageURL() {
    $pageURL = 'http';
    if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
      $pageURL .= "://";
    if ($_SERVER["SERVER_PORT"] != "80") {
      $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
    } else {
      $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
    }
    return $pageURL;
  }

  /**
   * Gets the liks of the images associated with the current post in all sizes
   **/
  private function get_image_size_links($post) {
  	/* Set up an empty array for the links. */
  	$links = array();
  	/* Get the intermediate image sizes and add the full size to the array. */
  	$sizes = get_intermediate_image_sizes();
    $sizes[] = 'full';

  	/* Loop through each of the image sizes. */
    $i = 0;
    foreach ( $sizes as $size ) {
      /* Get the image source, width, height, and whether it's intermediate. */
      remove_filter( 'image_downsize', array( Jetpack_Photon::instance(), 'filter_image_downsize' ) );
      if ($image = wp_get_attachment_image_src( $post->ID, $size )){

        $links[$i]['url'] = $image[0];
        $links[$i]['type'] = $size;
        $links[$i]['width'] = $image[1];
        $links[$i]['height'] = $image[2];

        $i++;
      }
      add_filter( 'image_downsize', array( Jetpack_Photon::instance(), 'filter_image_downsize' ), 10, 3 );
    }

    return $links;
  }


  /**
   * Returns the RDF graph associated with the Wordpress Post content type
   **/
  private function WP_Post_types($graph, $post) {
    if ($post->post_type == 'post') {
      $post_resource = $graph->resource($post->guid);
      $post_resource->add('rdf:type', $graph->resource('sioc:BlogPost'));
    }

    return $graph;
  }

  /**
   * Returns the RDF graph of the attachments associated with the current Post
   **/
  private function WP_Post_attachments($graph, $post) {
    $args = array( 'post_type' => 'attachment', 'numberposts' => null, 'post_status' => 'inherit', 'post_parent' => $post->ID );
    $attachments = get_posts($args);

    if ($attachments) {
      foreach ($attachments as $attachment) {
        $post_resource = $graph->resource($post->guid);
        $post_resource->add('sioc:attachment', $graph->resource($attachment->guid));

        $attachment_resource = $graph->resource($attachment->guid);
        $attachment_resource->set('rdfs:seeAlso', $graph->resource($this->return_seeAlso_resource(get_attachment_link($attachment->ID))));
      }
    }
    return $graph;
  }

  /**
   * Returns the RDF graph of the taxonomies associated with the current Post
   **/
  private function WP_Post_taxonomies($graph, $post) {
    $taxonomy_names = get_object_taxonomies( $post->post_type );
    foreach ( $taxonomy_names as $taxonomy_name ) {
      $terms = wp_get_post_terms($post->ID, $taxonomy_name);
      foreach ( $terms as $term ) {
        $post_resource = $graph->resource($post->guid);
        $post_resource->add('sioc:topic', $graph->resource(get_term_link( $term)));
        $taxonomy_resource = $graph->resource(get_term_link( $term));
        $taxonomy_resource->set('rdfs:seeAlso', $graph->resource($this->return_seeAlso_resource(get_term_link($term))));
      }
    }
    return $graph;
  }

  /**
   * Returns the RDF graph of the taxonomies associated with the current class instance
   **/
  private function stdClass_types($graph, $taxonomy) {
    if ($taxonomy->taxonomy == 'category') {
      $taxonomy_resource = $graph->resource(get_term_link( $taxonomy));
      $taxonomy_resource->add('rdf:type', $graph->resource('skos:Concept'));
      $taxonomy_resource->set('skos:prefLabel',  $taxonomy->name ?: null);
      $taxonomy_resource->set('skos:scopeNote',  $taxonomy->description ?: null);
      $taxonomy_resource->set('skos:inScheme',  $graph->resource( get_bloginfo("url")."/#categories") ?: null);
      if ($taxonomy->parent != "0"){
        $taxonomy_resource->set('skos:broader', get_category_link($taxonomy->parent) ?: null);
        $parentseealso = $graph->resource(get_category_link($taxonomy->parent));
        $parentseealso->set('rdfs:seeAlso', $this->return_seeAlso_resource(get_category_link($post_taxonomy->parent)) ?: null);
      }

      $subcategories = get_categories(array('parent' => $taxonomy->term_id, 'hide_empty' => 0));
      foreach ( $subcategories as $subcategory ) {
        $taxonomy_resource->add('skos:narrower', $graph->resource(get_category_link($subcategory->cat_ID) ?: null));
        $childseealso = $graph->resource(get_category_link($subcategory->cat_ID));
        $childseealso->set('rdfs:seeAlso', $graph->resource($this->return_seeAlso_resource(get_category_link($subcategory->cat_ID)) ?: null));
      }
    } elseif ($taxonomy->taxonomy == 'post_tag') {
      $post_resource = $graph->resource(get_term_link( $taxonomy));
      $post_resource->add('rdf:type', $graph->resource('sioc:Tag'));
    }

  return $graph;
  }

  /**
   * Returns the RDF graph associated with a STDClass typed content type
   **/
  public function do_content_stdClass($graph, $taxonomy){
    $taxonomy_resource = $graph->resource(get_term_link( $taxonomy));
    $graph = $this->stdClass_types($graph,$taxonomy);
    $taxonomy_resource->set('rdfs:label', $taxonomy->name ?: null);
    $taxonomy_resource->set('rdfs:comment', $taxonomy->description ?: null);

    return $graph;
  }

  /**
   * Returns the RDF graph associated with the Wordpress User content type
   **/
  public function do_content_WP_User($graph, $user){
    $authoruri = $this->return_user_uri($user->ID);
    $document = $graph->resource(get_author_posts_url($user->ID), 'foaf:PersonalProfileDocument');
    $document->set('dc:title', $user->display_name." FOAF Profile" ?: null);
    $document->set('foaf:primaryTopic', $graph->resource($authoruri));
    $user_resource = $graph->resource($authoruri,'sioc:UserAccount');
    $user_resource->set('sioc:name', $user->display_name ?: null);

    if (current_user_can('edit_user', $user->ID) ){
      $user_resource->set('foaf:givenName', $user->user_firstname ?: null);
      $user_resource->set('foaf:familyName', $user->user_lastname ?: null);
      $user_resource->set('foaf:nick', $user->nickname ?: null);
      $user_resource->set('bio:olb', $user->user_description ?: null);
      $graph = apply_filters( "lh_rdf_nodes", $graph, $authoruri,$user);
    }

    $document->set('foaf:maker', $graph->resource($authoruri));
    return $graph;
  }

  /**
   * Returns the RDF graph associated with the Wordpress Post content type
   **/
  public function do_content_WP_Post($graph, $post){
    $post_resource = $graph->resource($post->guid, 'sioc:Post');
    $graph = apply_filters("lh_rdf_nodes", $graph, $post->guid,$post);

    $graph = $this->WP_Post_types($graph,$post);
    $post_resource->set('dc:title', $post->post_title);
    $post_resource->set('dcterms:identifier', $post->ID );
    $post_resource->set ('dc:modified', \EasyRdf_Literal_Date::parse($post->post_modified));
    $post_resource->set ('dc:created', \EasyRdf_Literal_Date::parse($post->post_date));
    $post_resource->set('sioc:link', $graph->resource(get_the_permalink()));
    $post_resource->set('sioc:has_creator', $graph->resource($this->return_user_uri($post->post_author)));
    $post_resource->set('sioc:has_container', $graph->resource(get_bloginfo("url")."/#posts"));

    $authorseealso = $graph->resource($this->return_user_uri($post->post_author));
    $authorseealso->set('rdfs:seeAlso', $graph->resource($this->return_seeAlso_resource(get_author_posts_url($post->post_author)) ?: null));


    $post_resource->set('dc:abstract',strip_tags($post->post_excerpt));
    $post_resource->set('content:encoded',new EasyRdf_Literal_XML("<![CDATA[".do_shortcode($post->post_content)."]]>"));
    $post_resource->set('sioc:content',strip_tags(do_shortcode($post->post_content)));

    if ( has_post_thumbnail()) {
      $thumbnail = get_post( get_post_thumbnail_id());
      $post_resource->set('foaf:depiction', $graph->resource($thumbnail->guid));
      $graph->resource($thumbnail->guid)->set('rdfs:seeAlso', $graph->resource(add_query_arg('feed','lhrdf',get_attachment_link($thumbnail->ID)) ?: null));
    }

    $graph = $this->WP_Post_taxonomies($graph,$post);
    $graph = $this->WP_Post_attachments($graph,$post);
    return $graph;
  }

  /**
   * Returns the RDF graph associated with the Wordpress Attachment content type
   **/
  public function do_content_attachment($graph, $post){
    $post_resource = $graph->resource($post->guid);
    $post_resource->set('foaf:name', $post->post_title);
    $post_resource->set('dcterms:format',$graph->resource('http://purl.org/NET/mediatypes/'.get_post_mime_type($post->ID)));
    $post_resource->set('sioc:has_creator',$graph->resource($this->return_user_uri($post->post_author)));
    $graph->resource($this->return_user_uri($post->post_author))->set('rdfs:seeAlso', $graph->resource(add_query_arg('feed','lhrdf',get_author_posts_url($post->post_author)) ?: null));

    if (wp_attachment_is_image($post->ID)){
      $post_resource->set('rdf:type', $graph->resource('foaf:image'));
      $images = $this->get_image_size_links($post);
      foreach ( $images as $image ) {
        $post_resource->add('foaf:thumbnail', $graph->resource($image['url'], 'foaf:image'));
        $graph->resource($image['url'])->set('img:width',$image['width']);
        $graph->resource($image['url'])->set('img:height',$image['height']);
      }
  } else {
    $post_resource->set('rdf:type', $graph->resource('foaf:Document'));
  }

  $graph = apply_filters("lh_rdf_nodes", $graph, $post->guid);
  return $graph;
  }


  /**
   * Returns the RDF graph associated with the current specific Wordpress query
   **/
  public function do_content_wp_query($graph, $wp_query){
    $blogResource = $graph->resource(site_url(), 'sioc:Weblog');
    $blogResource->add('rdf:type',$graph->resource('http://rdfs.org/sioc/ns#Site'));
    $blogResource->add('rdf:type', $graph->resource('http://purl.org/spar/fabio/WebSite'));
    $blogResource->add('rdf:type', $graph->resource('http://purl.org/info:eu-repo/semantics/EnhancedPublication'));
    $blogResource->add('rdf:type', $graph->resource('http://www.openarchives.org/ore/terms/Aggregation'));
    $blogResource->set('dc:title', get_bloginfo('name') ?: null);
    $blogResource->set('dc:description', get_bloginfo('description') ?: null);

    $blogResource->add('ore:aggregates', $graph->resource(get_bloginfo("url")."/#posts"));

    $posts = $wp_query->get_posts();

    foreach($posts as $post) {
      $graph->resource(get_bloginfo("url")."/#posts")->add('sioc:container_of', $graph->resource($post->guid));
      $graph->resource($post->guid)->set('rdfs:seeAlso',$graph->resource($this->return_seeAlso_resource(get_permalink($post->ID))));
    }

    $blogResource->add('ore:aggregates', $graph->resource(get_bloginfo("url")."/#categories"));
    $conceptscheme = $graph->resource(get_bloginfo("url")."/#categories", 'skos:ConceptScheme');
    $conceptscheme->set('dc:date', mysql2date('Y-m-d\TH:i:s\Z', get_lastpostmodified('GMT'), false));

    $categories = get_categories(
      array(
        'parent' => 0,
        'hide_empty' => 0
      )
    );

    foreach ($categories as $category ) {
      $conceptscheme->add('skos:hasTopConcept',$graph->resource(get_category_link($category->cat_ID)));
      $graph->resource(get_category_link($category->cat_ID))->set('rdfs:seeAlso',$graph->resource($this->return_seeAlso_resource(get_category_link($category->cat_ID))));
    }

    return $graph;
  }


  /**
   * Unclear what the purpose of this method is
   **/
  public function do_content_datadump($graph) {
    return $graph;
  }

  /**
   * Default constructor, taking the format to use as argument
   **/
  function __construct($format = "rdfxml") {
    $this->format = $format;
  }
}

?>
