<?php
	/* Youtube Video Playlist Moodle Block
	 * Paul Holden, Greenhead College, 31st July 2007
	 * http://gcmoodle.greenhead.ac.uk/external/youtube/
	 * -------------------------------------------------
	 * block_youtube_video.php
	 * Contains the block generation code for displaying videos in courses
	 */

	class block_youtube_video extends block_base {

		var $video;

		function init() {
			$this->title = get_string('formaltitle', 'block_youtube_video');
			$this->version = 2007073100;
		}

		function specialization() {
			if (!empty($this->video) && !empty($this->video->title)) {
				$this->title = get_string('customtitle', 'block_youtube_video', $this->video->title);
			} else {
				$this->title = get_string('formaltitle', 'block_youtube_video');
			}
		}

		function instance_allow_config() {
			return true;
		}

		function applicable_formats() {
			return (array('all' => true, 'my' => false));
		}

		function get_video() {
                       
                        if(!empty($this->config->pls)){
			$playlist = explode(';', $this->config->pls);
			array_pop($playlist);
			return get_record('block_youtube_video', 'id', $playlist[rand() % count($playlist)]);
                        }
                        else{
                          return;  
                        }
		}

		function get_content() {
			if ($this->content !== null) {
				return $this->content;
			}

			$this->content = new stdClass;
			$this->video = $this->get_video();

			if (!$this->video) {
				$this->content->text = get_string('warning_no_playlist', 'block_youtube_video');
				$this->content->footer = '';
				return $this->content;
			}

			$this->specialization();

			$movieurl = str_replace(array('/watch/', '/watch?v='), '/v/' , $this->video->url);

			$filteropt = new stdClass;
			$filteropt->noclean = true;

			$this->content->text = '<div align="center">' .
						'<object width="250" height="210">' .
						 '<param name="movie" value="' . $movieurl . '"></param>' .
						 '<param name="wmode" value="transparent"></param>' .
						 '<embed src="' . $movieurl . '" type="application/x-shockwave-flash" wmode="transparent" width="250" height="210"></embed>' .
						'</object>' .
					       '</div>';
			$this->content->footer = format_text($this->video->description, FORMAT_MOODLE, $filteropt);

			return $this->content;

		}
	}
?>
