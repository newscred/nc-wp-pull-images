```
Plugin Name: Pull Images to WP Media Library
Description: This plugin will pull all inline images from post content to WP Media Library on post save.
Version: 1.0
Author: Rhythm Shahriar <rhythm@newscred.com>
License: MIT
```

**This plugin will only work when you are using `CMP` as your publishing service.

##### PLUGIN SETUP
```
1. Download the repo as zip
2. Go to Plugins > Add New > Upload Plugin
3. Activate Plugin
```

##### REQUIREMENTS
Since uploading images are the synchronous process you might have to increase resource limits in `php.ini`
but preferable limits are according to your usages blow limits are just

```
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
```

##### WHITELIST DOMAIN(S)
This plugin will pull images from only whitelisted domains (LINE:22)
``` 
$domain_whitelist = [
    'images-cdn.newscred.com',
    'images1.newscred.com',
    'images2.newscred.com',
    'images3.newscred.com',
    'images4.newscred.com',
];
```

##### BLACKLIST DOMAIN(S)
If you want to ignore any domain from being pulled, update the domain_whitelist array (LINE:33)
``` 
$domain_blacklist = [
    preg_replace( "(^https?://)", "", get_home_url() ),
];
```

##### WORK PROCESS
- For this use case, there are two possible actions: you are creating a new post or you are updating the post.
- If you are creating a new post, this will pull all the inline images from `img` src and create store those in the WordPress media library.
  After that, it will create a new custom field, `nc-cmp-ml-images`, and save a JSON of CMP & WP URL mapping so that we can track or reuse in future rater pull the same images multiple times.
- If you are updating the post, it will first check if there is any custom field  `nc-cmp-ml-images` to find out if the image(s) is/are already pulled.
  If there any match found, then instead of re-pull the images, this plugin will reuse those previous uploads. (To keep the Media Library clean) and will pull only new images
  for that post.

##### CHANGES
For custom post type if you are facing any issue, you can try with this hook https://developer.wordpress.org/reference/hooks/save_post_post-post_type/ 
```
save_post_{$post->post_type}
```