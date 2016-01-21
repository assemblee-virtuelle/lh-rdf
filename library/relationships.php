<?php

class LH_RDF_relationships_class {
  var $filename;
  var $opt_name = "lh_rdf-relationship_options";
  var $hidden_field_name = 'lh_rdf-relationship-submit_hidden';
  var $options;

  function list_types($type){
    $uniques = [];
    foreach ( P2P_Connection_Type_Factory::get_all_instances() as $p2p_type => $ctype ) {
      if (get_class($ctype->side['from']) == "P2P_Side_".ucfirst($type)){
        $uniques[] = $p2p_type;
      }
    }

    $array = [];
    foreach ($uniques as $unique){
      if ($this->options['type_mapping'][$unique]){
        $array[] = $unique;
      }
    }
    return $array;
  }

  function lh_rdf_graph($graph){
    //$foo = $graph->get('sioc:Post', '^rdf:type');
    //print_r($graph->resources());
    return $graph;
  }

  function lh_rdf_nodes($graph, $subject, $theobject){
    if ($theobject->caps){
      $connection_types = $this->list_types('user');
    } else {
      $connection_types = $this->list_types('post');
    }

    $connectedposts = new WP_Query(
      array(
        'connected_type' => $connection_types,
  	    'connected_items' => $theobject->ID,
  		  'post_type' => get_post_types()
        )
    );

    foreach ( $connectedposts->posts as $connectedpost ) {
      $graph->resource($subject)->set($this->options['type_mapping'][$connectedpost->p2p_type], $graph->resource(str_replace("&#038;", "&", $connectedpost->guid)));
      $graph->resource(str_replace("&#038;", "&", $connectedpost->guid))->set("rdfs:seeAlso", $graph->resource(get_permalink( $connectedpost->ID )."?feed=lhrdf"));
    }

    $connectedusers = new WP_User_Query(
      array(
        'connected_type' => $connection_types,
    		'connected_items' => $theobject->ID
      )
    );

    foreach ( $connectedusers->results as $user) {
      $graph->resource($subject)->set($this->options['type_mapping'][$user->p2p_type], $graph->resource(get_author_posts_url($user->ID)));
      $graph->resource(get_author_posts_url($user->ID))->set("rdfs:seeAlso", $graph->resource(get_author_posts_url($user->ID)."?feed=lhrdf"));
    }

    if ($theobject->caps){
      foreach($this->options['usermeta_mapping'] as $key => $value ){
        $user_metas = get_user_meta( $theobject->ID, $key);
        if (!empty($user_metas)){
          foreach($user_metas as $user_meta ){
            if (!is_array($user_meta)){
              $graph->resource($subject)->add($value,$user_meta);
            }
          }
          $graph = apply_filters( "lh_relationships_usermeta", $graph,$subject, $user_metas,$key,$value);
        }
      }
    } else {
      foreach($this->options['postmeta_mapping'] as $key => $value ){
        $post_metas = get_post_meta( $theobject->ID, $key);
        if (!empty($post_metas)){
          foreach($post_metas as $post_meta ){
            if (!is_array($post_meta)){
              $graph->resource($subject)->add($value,$post_meta);
            }
          }
          $graph = apply_filters( "lh_relationships_postmeta", $graph,$subject, $post_metas,$key,$value);
        }
      }
    }
    return $graph;
  }

  public function plugin_menu() {
    add_options_page('LH Rdf Mappings', 'LH Rdf Mappings', 'manage_options', $this->filename, array($this,"plugin_options"));
  }

  public function plugin_options() {
  	if (!current_user_can('manage_options'))  {
  		wp_die( __('You do not have sufficient permissions to access this page.') );
  	}

    if ( !is_plugin_active('posts-to-posts/posts-to-posts.php') ) {
      ?>
      __(In order for this plugin to work you need to install and activate the plugin <a href="https://wordpress.org/plugins/posts-to-posts/">Posts 2 Posts</a>.
      <?php
      wp_die();
    }

    // Now display the settings editing screen
    echo '<div class="wrap">';
    // header
    echo "<h1>" . __('LH Relationships Mappings', 'menu-test' ) . "</h1>";
    // settings form
    // See if the user has posted us some information
    // If they did, this hidden field will be set to 'Y'

    if( isset($_POST[  $this->hidden_field_name ]) && $_POST[  $this->hidden_field_name ] == 'Y' ) {
      $options = $this->options;

      if ($_POST['add_namespace-prefix'] and $_POST['add_namespace-uri'] and ($_POST['add_namespace-prefix'] != "") and ($_POST['add_namespace-uri'] != "")){
        $prefix = sanitize_text_field($_POST['add_namespace-prefix']);
        $options['namespaces'][$prefix] = sanitize_text_field($_POST['add_namespace-uri']);
      }

      if ($_POST[$this->opt_name."-add_p2p_type"] and $_POST[$this->opt_name."-add_type_uri"] and ($_POST[$this->opt_name."-add_p2p_type"] != "") and ($_POST[$this->opt_name."-add_type_uri"] != "")){
        $type= sanitize_text_field($_POST[$this->opt_name."-add_p2p_type"]);
        $options['type_mapping'][$type] = sanitize_text_field($_POST[$this->opt_name."-add_type_uri"]);
      }

      if ($_POST[ $this->opt_name."-add_postmeta_key"] and $_POST[$this->opt_name."-add_postmeta_uri"] and ($_POST[ $this->opt_name."-add_postmeta_key"] != "") and ($_POST[$this->opt_name."-add_postmeta_uri"] != "")){
        $key = sanitize_text_field($_POST[$this->opt_name."-add_postmeta_key"]);
        $options['postmeta_mapping'][$key] = sanitize_text_field($_POST[$this->opt_name."-add_postmeta_uri"]);
      }

      if ($_POST[ $this->opt_name."-add_usermeta_key"] and $_POST[$this->opt_name."-add_usermeta_uri"] and ($_POST[ $this->opt_name."-add_usermeta_key"] != "") and ($_POST[$this->opt_name."-add_usermeta_uri"] != "")){
        $key = sanitize_text_field($_POST[$this->opt_name."-add_usermeta_key"]);
        $options['usermeta_mapping'][$key] = sanitize_text_field($_POST[$this->opt_name."-add_usermeta_uri"]);
      }

      if (update_site_option( $this->opt_name, $options )){
        $this->options = get_site_option($this->opt_name);
        ?>
        <div class="updated"><p><strong><?php _e('LH Relationships settings saved', 'menu-test' ); ?></strong></p></div>
        <?php
      }
    }

    if( isset($_GET['lh_relationships-action']) && $_GET['lh_relationships-action'] == 'remove_option' ) {
      $options = $this->options;
      unset($options[$_GET['lh_relationships-option']][$_GET['lh_relationships-key']]);
      if (update_site_option( $this->opt_name, $options )){
        $this->options = get_site_option($this->opt_name);
        ?>
        <div class="updated"><p><strong><?php echo $_GET['lh_relationships-option']." ". $_GET['lh_relationships-key']." removed"; ?></strong></p></div>
        <?php
      }
    }
    echo "<h3>Namespace Mappings</h3>\n<ol>\n";
    foreach($this->options['namespaces'] as $key => $value ){
      echo "<li>".$key.": ".$value." <a href=\"".add_query_arg( 'lh_relationships-action', 'remove_option', add_query_arg( 'lh_relationships-option', 'namespaces', add_query_arg( 'lh_relationships-key', $key)))."\">remove</a></li>\n";
    }
    echo "</ol>\n";
    ?>
    <form name="lh_relationships-backend_form" method="post" action="<?php echo esc_url( remove_query_arg( array('lh_relationships-action','lh_relationships-option','lh_relationships-key')) ); ?>">
    <input type="hidden" name="<?php echo $this->hidden_field_name; ?>" value="Y" />
    <strong>Add Namespaces</strong>
    <p>
    <label for="add_namespace-prefix"><?php _e("Prefix:", 'menu-test' ); ?></label><input type="text" name="add_namespace-prefix" id="add_namespace-prefix" size="6" />
    <label for="add_namespace-uri"><?php _e("URI:", 'menu-test' ); ?></label><input type="url" name="add_namespace-uri" id="add_namespace-uri" size="40" />
    </p>
    <?php

    echo "<h3>Post Relationship Mappings</h3>\n<ol>\n";
    foreach($this->options['type_mapping'] as $key => $value ){
      echo "<li>".$key."--> ".$value." <a href=\"".add_query_arg( 'lh_relationships-action', 'remove_option', add_query_arg( 'lh_relationships-option', 'type_mapping', add_query_arg( 'lh_relationships-key', $key)))."\">remove</a></li>\n";
    }
    echo "</ol>\n";

    ?>
    <strong>Add Post Relationship Mappings</strong>
    <p>
      <?php _e("Connection Type:", 'menu-test' ); ?><select name="<?php echo $this->opt_name."-add_p2p_type"; ?>" id="<?php echo $this->opt_name."-add_p2p_type"; ?>" >
      <?php
      foreach ( P2P_Connection_Type_Factory::get_all_instances() as $p2p_type => $ctype ) {
        echo "<option value=\"".$p2p_type."\">".$p2p_type."</option>";
      }

      echo "</select>";
      _e("URI:", 'menu-test' ); ?><input type="url" name="<?php echo $this->opt_name."-add_type_uri"; ?>" id="<?php echo $this->opt_name."-add_type_uri"; ?>" size="40" />
    </p>
    <?php
    echo "<h3>Post Meta Mappings</h3>\n<ol>\n";
    foreach($this->options['postmeta_mapping'] as $key => $value ){
      echo "<li>".$key."--> ".$value." <a href=\"".add_query_arg( 'lh_relationships-action', 'remove_option', add_query_arg( 'lh_relationships-option', 'postmeta_mapping', add_query_arg( 'lh_relationships-key', $key)))."\">remove</a></li>\n";
    }
    echo "</ol>\n";
    ?>
    <strong>Add Post Meta Mappings</strong>
    <p>
      <?php _e("Post Meta Key:", 'menu-test' ); ?><input type="text" name="<?php echo $this->opt_name."-add_postmeta_key"; ?>" id="<?php echo $this->opt_name."-add_postmeta_key"; ?>" size="10" />
      <?php _e("URI:", 'menu-test' ); ?><input type="text" name="<?php echo $this->opt_name."-add_postmeta_uri"; ?>" id="<?php echo $this->opt_name."-add_postmeta_uri"; ?>" size="40" />
    </p>

    <?php
    echo "<h3>User Meta Mappings</h3>\n<ol>\n";
    foreach($this->options['usermeta_mapping'] as $key => $value ){
      echo "<li>".$key."--> ".$value." <a href=\"".add_query_arg( 'lh_relationships-action', 'remove_option', add_query_arg( 'lh_relationships-option', 'usermeta_mapping', add_query_arg( 'lh_relationships-key', $key)))."\">remove</a></li>\n";
    }
    echo "</ol>\n";
    ?>
    <strong>Add User Meta Mappings</strong>
    <p>
      <?php _e("User Meta Key:", 'menu-test' ); ?><input type="text" name="<?php echo $this->opt_name."-add_usermeta_key"; ?>" id="<?php echo $this->opt_name."-add_usermeta_key"; ?>" size="10" />
      <?php _e("URI:", 'menu-test' ); ?><input type="text" name="<?php echo $this->opt_name."-add_usermeta_uri"; ?>" id="<?php echo $this->opt_name."-add_usermeta_uri"; ?>" size="40" />
    </p>

    <input type="submit" name="submit" id="submit" value="save" />
    </form>

    <?php
      print_r($this->list_types('user'));
      echo '</div>';
  }



  // add a settings link next to deactive / edit
  public function add_settings_link( $links, $file ) {
  	if( $file == $this->filename ){
  		$links[] = '<a href="'. admin_url( 'options-general.php?page=' ).$this->filename.'">Settings</a>';
  	}
  	return $links;
  }


  public function lh_rdf_namespaces( $namespaces ) {
    foreach($this->options['namespaces'] as $key => $value ){
    $namespaces[$key] = $value;
    }
    return $namespaces;
  }

  function __construct() {
    $this->filename = plugin_basename( __FILE__ );
    $this->options = get_site_option($this->opt_name);
    add_filter( 'lh_rdf_nodes', array($this,"lh_rdf_nodes"), 10, 3 );
    add_filter( 'lh_rdf_graph', array($this,"lh_rdf_graph"), 10, 1 );
    add_filter( 'lh_rdf_namespaces', array($this,"lh_rdf_namespaces"));
    add_action('admin_menu', array($this,"plugin_menu"));
    add_filter('plugin_action_links', array($this,"add_settings_link"), 10, 2);
  }
}


$lh_rdf_mappings_instance = new LH_RDF_relationships_class();


?>
