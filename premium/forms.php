<?php

define( 'MWAI_FORMS_FRONT_PARAMS', [ 'id', 'label', 'type', 'name', 'options', 'copyButton', 'localMemory',
  'required', 'placeholder', 'default', 'maxlength', 'rows', 'outputElement', 'accept', 'customAccept', 'multiple' ] );
define( 'MWAI_FORMS_SERVER_PARAMS', [ 'model', 'temperature', 'maxTokens', 'prompt', 'message',
  'envId', 'scope', 'resolution', 'message', 'assistantId',
  'embeddingsIndex', 'embeddingsEnv', 'embeddingsEnvId', 'embeddingsNamespace'
] );

class MeowPro_MWAI_Forms {
  private $core = null;
  private $namespace = 'mwai-ui/v1';

  function __construct() {
    global $mwai_core;
    $this->core = $mwai_core;
    add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
    if ( MeowCommon_Helpers::is_asynchronous_request() ) {
      return;
    }
    add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
    add_shortcode( 'mwai-form-field', array( $this, 'shortcode_mwai_form_field' ) );
    add_shortcode( 'mwai-form-upload', array( $this, 'shortcode_mwai_form_upload' ) );
    add_shortcode( 'mwai-form-submit', array( $this, 'shortcode_mwai_form_submit' ) );
    add_shortcode( 'mwai-form-reset', array( $this, 'shortcode_mwai_form_reset' ) );
    add_shortcode( 'mwai-form-output', array( $this, 'shortcode_mwai_form_output' ) );
    add_shortcode( 'mwai-form-container', array( $this, 'shortcode_mwai_form_container' ) );
  }

  public function register_scripts() {
		$physical_file = trailingslashit( MWAI_PATH ) . 'premium/forms.js';	
		$cache_buster = file_exists( $physical_file ) ? filemtime( $physical_file ) : MWAI_VERSION;
		wp_register_script( 'mwai_forms', trailingslashit( MWAI_URL ) . 'premium/forms.js',
			[ 'wp-element' ], $cache_buster, false );
	}

  public function enqueue_scripts() {
		wp_enqueue_script( "mwai_forms" );
	}

  function clean_params( &$params ) {
		foreach ( $params as $param => $value ) {
			if ( empty( $value ) || is_array( $value ) ) {
				continue;
			}
			$lowerCaseValue = strtolower( $value );
			if ( $lowerCaseValue === 'true' || $lowerCaseValue === 'false' || is_bool( $value ) ) {
				$params[$param] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
			}
			else if ( is_numeric( $value ) ) {
				$params[$param] = filter_var( $value, FILTER_VALIDATE_FLOAT );
			}
		}
		return $params;
	}

  public function fetch_system_params( $id ) {
		$frontSystem = [
			'id' => $id,
			'userData' => $this->core->get_user_data(),
			'sessionId' => $this->core->get_session_id(),
			'restNonce' => $this->core->get_nonce(),
			'contextId' => get_the_ID(),
			'pluginUrl' => MWAI_URL,
			'restUrl' => untrailingslashit( get_rest_url() ),
			'debugMode' => $this->core->get_option( 'module_devtools' ) && $this->core->get_option( 'debug_mode' ),
			'stream' => $this->core->get_option( 'ai_streaming' ),
		];
		return $frontSystem;
	}

  function rest_api_init() {
		try {
			register_rest_route( $this->namespace, '/forms/submit', array(
				'methods' => 'POST',
				'callback' => array( $this, 'rest_submit' ),
        'permission_callback' => '__return_true'
			) );
		}
		catch ( Exception $e ) {
			var_dump( $e );
		}
	}

  function rest_submit( $request ) {
    try {
      $params = $request->get_json_params();

      $context = null;
      $id = $params['id'] ?? null;
      $stream = $params['stream'] ?? false;
      $fields = $params['fields'] ?? [];
      $uploadFields = $params['uploadFields'] ?? [];
  
      // 1) Retrieve system params from the transient
      $systemParams = get_transient( 'mwai_custom_form_' . $id ) ?? [];
      $systemParams['prompt'] = $systemParams['prompt'] ?? "";
      $systemParams['message'] = $systemParams['message'] ?? "";
      $model = isset( $systemParams['model'] ) ? $systemParams['model'] : null;
  
      if ( !empty( $systemParams['prompt'] ) ) {
        Meow_MWAI_Logging::deprecated( 'The "prompt" parameter is deprecated. Please use the "message" parameter instead.' );
        $systemParams['message'] = $systemParams['prompt'];
      }
      if ( !empty( $params['prompt'] ) ) {
        Meow_MWAI_Logging::deprecated( 'The "prompt" parameter is deprecated. Please use the "message" parameter instead.' );
        $systemParams['message'] = $params['prompt'];
      }
  
      // 2) Prepare the message (based on the fields).
      $message = isset( $params['message'] ) ? $params['message'] : $systemParams['message'] ?? "";
      foreach ( $fields as $name => $value ) {
        if ( $value === null ) { continue; }
        if ( is_array( $value ) ) {
          $value = implode( ",", $value );
        }
        $name = "{" . $name . "}";
        $message = str_replace( '$' . $name, $value, $message );
        $message = str_replace( $name, $value, $message );
      }

      // Remove any remaining placeholders (upload fields)
      foreach ( $uploadFields as $name => $value ) {
        $name = '${' . $name . '}';
        $message = str_replace( $name, '', $message );
      }
  
      // 3) Finalize $systemParams => $params
      $systemParams['message'] = $message;
      $systemParams['scope'] = empty( $systemParams['scope'] ) ? 'form' : $systemParams['scope'];
      $newParams = [];
      foreach ( $systemParams as $key => $value ) {
        $newParams[$key] = $value;
      }
      foreach ( $params as $key => $value ) {
        $newParams[$key] = $value;
      }
      $params = apply_filters( 'mwai_forms_submit_params', $newParams );
  
      // 4) Retrieve model info
      $envId = $params['envId'] ?? null;
      $engine = Meow_MWAI_Engines_Factory::get( $this->core, $envId );
      $modelObj = $engine->retrieve_model_info( $model );
      if ( !empty( $envId ) && empty( $modelObj ) ) {
        return new WP_REST_Response([ 'success' => false, 'message' => 'Model not found.' ], 500 );
      }
      $modelFeatures = isset( $modelObj['features'] ) ? $modelObj['features'] : [];
      $isTextToImage = in_array( 'text-to-image', $modelFeatures );
      $isSpeechToText = in_array( 'speech-to-text', $modelFeatures );
  
      // 5) Build the Query object
      $query = null;
      if ( $isTextToImage ) {
        $query = new Meow_MWAI_Query_Image( $message, $model );
        $query->inject_params( $params );
      }
      else if ( $isSpeechToText ) {
        $query = new Meow_MWAI_Query_Transcribe( $message );
        $query->inject_params( $params );
        $query->set_message( "" );
        $query->set_url( $message );
      }
      else {
        $query = !empty( $params['assistantId'] )
          ? new Meow_MWAI_Query_Assistant( $message )
          : new Meow_MWAI_Query_Text( $message, 4096 );
        $query->inject_params( $params );
  
        // If there's context from embeddings
        $context = $this->core->retrieve_context( $params, $query );
        if ( !empty( $context ) ) {
          $query->set_context( $context['content'] );
        }
      }
  
      // 6) Optional: If there's an uploaded image (or doc), feed it to the query
      // We'll loop over each field in $uploadFields. For each field => files array
      foreach ( $uploadFields as $fieldName => $fileArray ) {
        // If there's more than 1 file, throw an error
        if ( count( $fileArray ) > 1 ) {
          return new WP_REST_Response([
            'success' => false,
            'message' => 'Multiple files are not supported for field: ' . $fieldName
          ], 400 );
        }
        // If there's exactly 1
        if ( count( $fileArray ) === 1 ) {
          $fileInfo = $fileArray[0];  // e.g. [ 'id' => 'abc123', 'url' => '...' ]
          $fileId = $fileInfo['id'];
          $fileUrl = $fileInfo['url'];
  
          // Pass it to the query
					$mimeType = $this->core->files->get_mime_type( $fileId );
          $isIMG = in_array( $mimeType, [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ] );
					$purposeType = $isIMG ? 'vision' : 'files';
          $query->set_file( Meow_MWAI_Query_DroppedFile::from_url( $fileUrl, $purposeType, 'image/jpeg' ) );
          $fileId = $this->core->files->get_id_from_refId( $fileId );
          $this->core->files->update_envId( $fileId, $engine->envId );
          $this->core->files->update_purpose( $fileId, $purposeType );
          $this->core->files->add_metadata( $fileId, 'query_envId', $engine->envId );
          $this->core->files->add_metadata( $fileId, 'query_session', $query->session );
        }
      }
  
      // 7) Attach the 'fields' as an extra param for your own usage
      $query->setExtraParam( 'fields', $fields );
  
      // 8) Handle streaming callback
      $streamCallback = null;
      if ( $stream ) {
        $streamCallback = function( $reply ) use ( $query ) {
          $raw = $reply;
          $this->core->stream_push( [ 'type' => 'live', 'data' => $raw ], $query );
        };
        header( 'Cache-Control: no-cache' );
        header( 'Content-Type: text/event-stream' );
        header( 'X-Accel-Buffering: no' );
        ob_implicit_flush( true );
        ob_end_flush();
      }
  
      // 9) Possible takeover
      $takeoverAnswer = apply_filters( 'mwai_form_takeover', null, $query, $params );
      if ( !empty( $takeoverAnswer ) ) {
        return new WP_REST_Response([
          'success' => true,
          'reply' => $takeoverAnswer,
          'images' => null,
          'usage' => null
        ], 200 );
      }
  
      // 10) Finally, query the AI
      $reply = $this->core->run_query( $query, $streamCallback, true );
      $rawText = $reply->result;
      $extra = [];
      if ( $context && isset( $context['embeddings'] ) ) {
        $extra = [ 'embeddings' => $context['embeddings'] ];
      }
      $rawText = apply_filters( 'mwai_form_reply', $rawText, $query, $params, $extra );
  
      $restRes = [
        'success' => true,
        'reply'   => $rawText,
        'images'  => $reply->get_type() === 'images' ? $reply->results : null,
        'usage'   => $reply->usage
      ];
  
      if ( $stream ) {
        $this->core->stream_push( [ 'type' => 'end', 'data' => json_encode( $restRes ) ], $query );
        die();
      }
      else {
        return new WP_REST_Response( $restRes, 200 );
      }
    }
    catch ( Exception $e ) {
      $message = apply_filters( 'mwai_ai_exception', $e->getMessage() );
      if ( $stream ) {
        $this->core->stream_push( [ 'type' => 'error', 'data' => $message ], $query );
      }
      else {
        return new WP_REST_Response([ 'success' => false, 'message' => $message ], 500 );
      }
    }
  }
  

   // Rename the keys of the atts into camelCase to match the internal params system.
  function keys_to_camel_case( $atts ) {
    $atts = array_map( function( $key, $value ) {
			$key = str_replace( '_', ' ', $key );
			$key = ucwords( $key );
			$key = str_replace( ' ', '', $key );
			$key = lcfirst( $key );
			return [ $key => $value ];
		}, array_keys( $atts ), $atts );
		$atts = array_merge( ...$atts );
    return $atts;
  }

  function fetch_front_params( $atts ) {
    $frontParams = [];
    foreach ( MWAI_FORMS_FRONT_PARAMS as $param ) {
			if ( isset( $atts[$param] ) ) {
				$frontParams[$param] = $atts[$param];
			}
		}
    $frontParams = $this->clean_params( $frontParams );
    return $frontParams;
  }
  
  function fetch_server_params( $atts ) {
    $serverParams = [];
    foreach ( MWAI_FORMS_SERVER_PARAMS as $param ) {
      if ( isset( $atts[$param] ) ) {
        $serverParams[$param] = $atts[$param];
        if ( $param === 'message' ) {
          $serverParams[$param] = urldecode( $serverParams[$param] );
        }
      }
    }
    $serverParams = $this->clean_params( $serverParams );
    return $serverParams;
  }

  function encore_params_for_html( $params ) {
    $params = htmlspecialchars( json_encode( $params ), ENT_QUOTES, 'UTF-8' );
    return $params;
  }

  public function shortcode_mwai_form_upload( $atts ) {
    $atts = apply_filters( 'mwai_forms_upload_params', $atts );
    $atts = $this->keys_to_camel_case( $atts );
    $frontParams = $this->fetch_front_params( $atts );
  
    // If you want to handle server-side params for the upload field (e.g., store them),
    // you can do so here by calling $this->fetch_server_params( $atts ), similar to
    // how you do in shortcode_mwai_form_submit(). But most often for a simple file input,
    // front-end usage is enough.
  
    // If you support a custom theme, handle it like your other blocks
    $theme = isset( $frontParams['themeId'] )
      ? $this->core->get_theme( $frontParams['themeId'] )
      : null;
  
    // Encode as JSON for your forms.js or React code
    $jsonFrontParams = $this->encore_params_for_html( $frontParams );
    $jsonFrontTheme  = $this->encore_params_for_html( $theme );
  
    // Ensure the forms.js script is loaded
    $this->enqueue_scripts();
  
    // Return a simple container div. JS will take that data-params and render <FormUpload />
    return "<div class='mwai-form-upload-container'
      data-params='{$jsonFrontParams}'
      data-theme='{$jsonFrontTheme}'></div>";
  }  

  // Based on the id, label, type, name and options, it will return the HTML code for the field.
  function shortcode_mwai_form_field( $atts ) {
    $atts = apply_filters( 'mwai_forms_field_params', $atts );
    $atts = $this->keys_to_camel_case( $atts );
    $frontParams = $this->fetch_front_params( $atts );

    // Client-side: Prepare JSON for Front Params and System Params
		$theme = isset( $frontParams['themeId'] ) ? $this->core->get_theme( $frontParams['themeId'] ) : null;
		$jsonFrontParams = $this->encore_params_for_html( $frontParams );
		$jsonFrontTheme = $this->encore_params_for_html( $theme );

    $this->enqueue_scripts();
		return "<div class='mwai-form-field-container' data-params='{$jsonFrontParams}' 
      data-theme='{$jsonFrontTheme}'></div>";
  }

  function shortcode_mwai_form_submit( $atts ) {
    $id = 'mwai-' . uniqid();
    $atts = apply_filters( 'mwai_form_params', $atts );
    $atts = $this->keys_to_camel_case( $atts );
    $frontParams = $this->fetch_front_params( $atts );
    $systemParams = $this->fetch_system_params( $id ); // Overridable by $atts later
    $serverParams = $this->fetch_server_params( $atts );

    // Extract the fields and selectors from the message, and build the inputs object.
    $message = $serverParams['message'];
    $inputs = [ 'fields' => [], 'selectors' => [] ];
    $matches = [];
    preg_match_all( '/{([A-Za-z0-9_-]+)}/', $message, $matches );
    foreach ( $matches[1] as $match ) {
      $inputs['fields'][] = $match;
    }
    $matches = [];
    preg_match_all( '/\$\{([^}]+)\}/', $message, $matches );
    foreach ( $matches[1] as $match ) {
      $inputs['selectors'][] = $match;
    }
    $frontParams['inputs'] = $inputs;

    // Server-side: Keep the System Params
		if ( count( $serverParams ) > 0 ) {
      $id = md5( json_encode( $serverParams ) );
      $systemParams['id'] = $id;
      $systemParams['inputs'] = $inputs;
			set_transient( 'mwai_custom_form_' . $id, $serverParams, 60 * 60 * 24 );
		}

    // Client-side: Prepare JSON for Front Params and System Params
		$theme = isset( $frontParams['themeId'] ) ? $this->core->get_theme( $frontParams['themeId'] ) : null;
    $jsonFrontParams = $this->encore_params_for_html( $frontParams );
    $jsonFrontSystem = $this->encore_params_for_html( $systemParams );
    $jsonFrontTheme = $this->encore_params_for_html( $theme );

    $this->enqueue_scripts();
		return "<div class='mwai-form-submit-container' 
      data-params='{$jsonFrontParams}' data-system='{$jsonFrontSystem}' 
      data-theme='{$jsonFrontTheme}'>
    </div>";
  }

  public function shortcode_mwai_form_reset( $atts ) {
    // Generate a unique ID for this reset block, if not provided
    $resetId = 'mwai-reset-' . uniqid();

    // Let plugins/themes modify the atts if needed
    $atts = apply_filters( 'mwai_form_reset_params', $atts );

    // Convert keys like "local_memory" => "localMemory"
    $atts = $this->keys_to_camel_case( $atts );

    // For front-end display/usage
    $frontParams = $this->fetch_front_params( $atts );

    // For system usage. We'll default to using the same unique $resetId for both
    $systemParams = $this->fetch_system_params( $resetId, $resetId );

    // If you have any special serverParams to parse, do that:
    // (Likely not needed for a reset button, but you can adapt as needed)
    $serverParams = $this->fetch_server_params( $atts );
    
    // If you want to set a stable `id` in $systemParams:
    $systemParams['id'] = md5( json_encode( $serverParams ) );
    $systemParams['resetId'] = $resetId;

    // If you do NOT need to store anything server side, you can skip set_transient().
    // But here's an example if you do:
    // set_transient( 'mwai_custom_reset_' . $systemParams['id'], $serverParams, 60 * 60 * 24 );

    // Prepare JSON for front & system usage
    $jsonFrontParams = $this->encore_params_for_html( $frontParams );
    $jsonFrontSystem = $this->encore_params_for_html( $systemParams );

    // Enqueue your needed scripts/styles
    $this->enqueue_scripts();

    // Return a container with data attributes, similarly to your submit function
    return "<div class='mwai-form-reset-container'
      data-params='{$jsonFrontParams}' data-system='{$jsonFrontSystem}'>
    </div>";
  }

  function shortcode_mwai_form_output( $atts ) {
    //$atts = apply_filters( 'mwai_forms_output_params', $atts );
    $atts = $this->keys_to_camel_case( $atts );
    $frontParams = $this->fetch_front_params( $atts );

    // Client-side: Prepare JSON for Front Params and System Params
		$theme = isset( $frontParams['themeId'] ) ? $this->core->get_theme( $frontParams['themeId'] ) : null;
		$jsonFrontParams = $this->encore_params_for_html( $frontParams );
		$jsonFrontTheme = $this->encore_params_for_html( $theme );

    $this->enqueue_scripts();
		return "<div class='mwai-form-output-container' data-params='{$jsonFrontParams}' 
      data-theme='{$jsonFrontTheme}'></div>";
  }

  function shortcode_mwai_form_container( $atts ) {
    $this->core->enqueue_themes();
    $id = empty( $atts['id'] ) ? uniqid() : $atts['id'];
    $theme = strtolower( $atts['theme'] );
    // A little hack, to make sure the theme is applied.
    $style_content = "<script>
      document.addEventListener('DOMContentLoaded', function() {
        var containers = document.querySelectorAll('#mwai-form-container-{$id}');
        if ( containers.length > 1 ) {
          console.warn('Multiple form containers found.', { id: '{$id}' });
        }
        if ( containers.length === 0 ) {
          console.warn('Form container not found.', { id: '{$id}' });
        }
        else {
          for ( var i = 0; i < containers.length; i++ ) {
            var container = containers[i];
            container.classList.add('mwai-" . $theme .  "-theme');
          }
        }
      });
    </script>";
    $style_content = apply_filters( 'mwai_forms_style', $style_content, $id );
    return $style_content;
  }
}
