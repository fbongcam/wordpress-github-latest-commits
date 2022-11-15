<?php

/*
Plugin Name: Github Latest Commits
Description: Gets the latest commits of a github repo
Version: 0.1
Author: @fbongcam
License: GPL2
*/

class github_latest_commits extends WP_Widget {
    private $isRepoValid = false;

    public function __construct() {
        parent::WP_Widget(
            'github_latest_commits',
            'Github Latest Commits',
            ['description' => __('Gets the latest commits of a github repo')]
        );
    }

    function widget( $args, $instance ) {
        $apiResult = githubCURL($instance);
        if ($apiResult)
        {
            // Construct HTML structure for commit
            $id_increment;
            ?>
            <div id="github-latest-commits-container widget widget-block">
                <?php
                foreach ($apiResult as $commit)
                {
                    $message = $commit['message'];
                    if (strlen($message) > 20)
                    {
                        // Reduce message length for 40 chars
                        $message = substr($message, 0, 20) . '...';
                    }
                    // Format date
                    $date = date_create($commit['date']);
                    $date = date_format($date,"Y/m/d H:i");
                    $id_increment++;
                    echo '<div id="github-commit-id-' . $id_increment . '" class="github-latest-commits-commit">';
                    echo '<div class="github-commit-author-date">';
                    echo '<div class="github-commit-author">' . $commit['author'] . '</div>';
                    echo '<div class="github-commit-date">' . $date . '</div>';
                    echo '</div>';
                    echo '<div class="github-commit-message">' . $message . '</div>';
                    echo '</div>';
                }
                ?>
            </div>
            <?php
            echo $before_title . $title . $after_title;
        }

    }

    function form( $instance ) {
        $repo = $instance['repo'];
        // Display error if repo URL not valid
        if (!empty($repo))
        {
            if (!validateURL($instance['repo']))
            {
                $isRepoValid = false;
            }
            else
            {
                $isRepoValid = true;
            }
        }
        ?>
        <p>
            <label for="<?php echo $this->get_field_id( 'repo' ); ?>"><?php _e("Repo URL"); ?>:</label>
            <input id="<?php echo $this->get_field_id( 'repo' ); ?>" name="<?php echo $this->get_field_name( 'repo' ); ?>" value="<?php echo $instance['repo']; ?>"
            <?php /* Add red background color if not valid */ if ($isRepoValid != true && $repo !== "") { echo 'style="width:100%;border-color: red;"'; } else { echo 'style="width:100%;"'; } ?> class="wp-block-search__input github-latest-commits-input github-latest-commits-repo-input" />
            <label for="<?php echo $this->get_field_id( 'oauth' ); ?>"><?php _e("Personal Access Token"); ?>:</label>
            <input id="<?php echo $this->get_field_id( 'oauth' ); ?>" name="<?php echo $this->get_field_name( 'oauth' ); ?>" value="<?php echo $instance['oauth']; ?>" style="width:100%;" class="wp-block-search__input github-latest-commits-input github-latest-commits-oauth-input" />
            <span class="github-latest-commits-error-msg"><?php if ($isRepoValid != true && $repo !== "") { echo 'Not a valid repo URL.'; } ?></span>
        </p>
        <?php
    }

    function update( $new_instance, $old_instance ) {
        $instance = [];
        $instance['repo'] = !empty($new_instance['repo']) ? strip_tags($new_instance['repo']) : '';
        $instance['apiURL'] = !empty(apiURL($new_instance['repo'])) ? strip_tags(apiURL($new_instance['repo'])) : '';
        $instance['oauth'] = !empty($new_instance['oauth']) ? strip_tags($new_instance['oauth']) : '';
        return $instance;
    }

}

/*
    Creates Github API call
    @param  -   instance containing saved data
    @return -   result of API call
*/
function githubCURL($instance)
{
    $apiURL = $instance['apiURL'];
    $oauth = $instance['oauth'];
    if (!empty($apiURL) && !empty($oauth))
    {
        // Va
        /*
            CURL Command
            curl --request GET \
            --url "https://api.github.com/repos/octocat/Spoon-Knife/issues" \
            --header "Accept: application/vnd.github+json" \
            --header "Authorization: Bearer YOUR-TOKEN"
        */
        $header = array(
            "Accept: application/vnd.github+json",
            "Authorization: Bearer " . $oauth,
        );
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL             =>  $apiURL,
            CURLOPT_HTTPGET         =>  true,
            CURLOPT_USERAGENT       =>  $_SERVER['HTTP_USER_AGENT'],
            CURLOPT_RETURNTRANSFER  =>  1,
            CURLOPT_HTTPHEADER      =>  $header,
        ));
        $result = curl_exec($curl);
        curl_close($curl);

        // Decode json
        $json = json_decode($result,true);
        if (json_last_error() !== JSON_ERROR_NONE) // Trigger error if result not of type JSON
        {
            return false;
        }

        // Get 5 latest commits
        $commits = [];
        $counter = 0;
        foreach ($json as $key => $value)
        {
            // Break when 5 commits collected
            if ($counter == 5)
            {
                break;
            }

            $commit = array(
                'author'    =>  $value['commit']['author']['name'],     // Author
                'message'   =>  $value['commit']['message'],            // Message
                'date'      =>  $value['commit']['committer']['date'],  // Date
            );
            array_push($commits, $commit);

            // Increment loop counter
            $counter++;
        }
        return $commits;
    }
    return false;
}

/*
    Validates entered repo URL
    @param  -   Repo URL
    @return -   true or false
*/
function validateURL($url)
{
    $regex_REPO_URL = "/http[s]?:[\/]{2}github\.com\/[A-z0-9|\-|\_]+\/[A-z0-9|\-|\_]+$/";
    return preg_match($regex_REPO_URL, $url);
}

/*
    Rebuilds repo URL to API URL
    @param  -   Repo URL
    @return -   API URL
*/
function apiURL($url)
{
    $pattern = "/:[\/]{2}github[\.]com[\/]/";
    $replace = "://api.github.com/repos/";
    $url = preg_replace($pattern, $replace, $url) . '/commits';
    return $url;
}

/*
    Adds custom JS scripts and CSS Styles to admin panel
*/
function adminJS_CSS()
{
    wp_enqueue_style('github-latest-commits-admin', plugins_url('/css/github-latest-commits-admin.css',__FILE__ ));
    wp_enqueue_script('github-latest-commits', plugins_url('/js/github-latest-commits.js',__FILE__ ), null, '', true);
}

function JS_CSS()
{
    wp_enqueue_style('github-latest-commits', plugins_url('/css/github-latest-commits.css',__FILE__ ));
}

/*
    Initializes the widget
*/
function init() {
    register_widget( 'github_latest_commits' );
}

add_action( 'widgets_init', 'init' );
add_action('admin_enqueue_scripts', 'adminJS_CSS');     // Enqueue JS Scripts and CSS in admin panel
add_action('wp_enqueue_scripts', 'JS_CSS');             // Enqueue JS Scripts and CSS


?>
