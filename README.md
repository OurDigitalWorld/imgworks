ImgWorks
========

Zend is a well known Framework for PHP and generally  fits well into any
Drupal-friendly environment. ODW has some legacy Cocoon applications that
provide java-based image manipulations, including extracting and
highlighting images. In our current operating environment, the java
approach is not ideal. Although there are options to integrate Zend
directly into Drupal, it adds to the complexity of the Drupal installation
and our preference is to keep the application standalone at this point.

The use of Zend taps into the GD graphics library via PHP, an efficient
option in our environment. Still, GD doesn't require Zend, where it becomes
necessary is for a native implementation of Lucene. Highlights are placed
on images based on coordinates stored in a Lucene index and the solution
needs to be able to invoke Lucene queries efficiently. Zend has a native
Lucene library and although the Lucene version support is dated (2.x), our
use in this case is relatively simple.

I followed the common approach for creating a skeleton application:

```
git clone git://github.com/zendframework/ZendSkeletonApplication.git ImgWorks
```

And then worked from there. If you download the distribution and do
something like:

```
cd ImgWorks
curl -s https://getcomposer.org/installer | php
```

The needed pieces will hopefully fall into place.

The layout of the application is largely defined in
[ImgWorks/module/ImgHl/config/module.config.php] (https://github.com/OurDigitalWorld/imgworks/blob/master/ImgWorks/module/ImgHl/config/module.config.php).
There are current three outputs from the application based on three
actions:

```
* _img_ - highlight search terms on specified region of image
* _json_ - return coordinates in json for search terms in specified region
* _cut_ - extract specified region of image
```

The URL pattens all use _action_/_site_/_collection_/_reel_/_page_/_w_/_h_
but vary slightly depending on whether a query is being processed (_img_
and _json_). The _cut_ action uses _x_ and _y_ parameters for specifying
the starting coordinates of the rectangle that will be extracted. This
leads to URLs that look like this (the search query here is _town_):

```
* img - http://mysite.org/ImgWorks/imghl/img/ink/newspapers/bmerchant/03_1871/BM-1871-03-24-03/600/400/town
* json - http://mysite.org/ImgWorks/imghl/json/ink/newspapers/bmerchant/03_1871/BM-1871-03-24-03/600/400/town
* cut - http://mysite.org/ImgWorks/imghl/json/ink/newspapers/bmerchant/03_1871/BM-1871-03-24-03/600/400?x=200&y=300
```

The business logic is contained in 
[module/ImgHl/src/ImgHl/Controller/ImgHlController.php] (https://github.com/OurDigitalWorld/imgworks/blob/master/ImgWorks/module/ImgHl/src/ImgHl/Controller/ImgHlController.php). 
The flow is fairly straightforward, it would be worth exploring some
different approaches to identifying the best _cluster_ for showing
highlights on an image but it's a simple count to determine the most stems 
for now.

art rhyno [ourdigitalworld/u. of windsor] (https://github.com/artunit)
