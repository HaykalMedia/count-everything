Count Everything
================

WordPress plugin to async counting of visits to each post.

## Installation

If you are using [composer](http://getcomposer.org) add this line to the required section:
```
"haykalmedia/count-everything": "dev-master",
```

**Note:** the package has not been added to composer main repositories, so you have to explicit declare the repository as *vcs*, to do that, add **repository** section in composer if not already exists, then add the package git url, you will have something similar to:
```json
"name": "your-package-name",
"repositories": [
    {
        "type": "vcs",
        "url":  "git@github.com:HaykalMedia/count-everything.git"
    }
],
"require": {
    "haykalmedia/count-everything": "dev-master",
}
```

If you are not using composer, then you can download [zip file](https://github.com/HaykalMedia/count-everything/archive/master.zip) and extract it in `wp-content/plugins` folder.

## Usage

You can now order posts by popular ones if you are using `WP_Query` by adding a query var `popular_posts` or `popular_posts_total` to the args or by setting the order by variable to `popular_posts`.

By default the query order posts by most popular posts in the current year.

To get most popular posts in the last week, add popular_posts query var and assign `week` as its value.