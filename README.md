ImgWorks
========

[Zend](http://framework.zend.com/) is a well known Framework for PHP and 
generally  fits well into any Drupal-friendly environment. 
[ODW](http://ourdigitalworld.org/) has some legacy 
[Cocoon](http://cocoon.apache.org/) applications that provide java-based 
image manipulations, including extracting and
highlighting images. In our current operating environment, the java
approach is not ideal. Although there are options to integrate Zend
directly into Drupal, it adds to the complexity of the Drupal installation
and our preference is to keep the application standalone at this point.

The use of Zend taps into the [GD graphics library](http://libgd.github.io/)
via PHP, an efficient option in our environment. Still, GD doesn't require 
Zend, where it becomes necessary is for a 
[native implementation of Lucene](https://github.com/zendframework/ZendSearch). 
Highlights are placed on images based on coordinates stored in a 
Lucene index and the solution
needs to be able to invoke Lucene queries efficiently. Although the Zend 
Lucene library support is somewhat dated (2.x), our
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
[ImgWorks/module/ImgHl/config/module.config.php](https://github.com/OurDigitalWorld/imgworks/blob/master/ImgWorks/module/ImgHl/config/module.config.php).
There are current four outputs from the application based on four
actions:

* _img_ - highlight search terms on specified region of image
* _json_ - return coordinates in json for search terms in specified region
* _cut_ - extract specified region of image
* _ol_ - provide tiles in response to OpenLayers 3.x requests

The URL pattens all use _action_/_site_/_collection_/_reel_/_page_/_w_/_h_
but vary slightly depending on whether a query is being processed (_img_
and _json_). The _cut_ action uses _x_ and _y_ parameters for specifying
the starting coordinates of the rectangle that will be extracted. The
OpenLayers tiles are typically 256x256 but here the height and width of the
source image are passed in. This leads to URLs that look like this:

* _img_ - http://mysite.org/ImgWorks/imghl/img/ink/newspapers/bmerchant/03_1871/BM-1871-03-24-03/600/400/town
* _json_ - http://mysite.org/ImgWorks/imghl/json/ink/newspapers/bmerchant/03_1871/BM-1871-03-24-03/600/400/town
* _cut_ - http://mysite.org/ImgWorks/imghl/cut/ink/newspapers/bmerchant/03_1871/BM-1871-03-24-03/600/400?x=200&y=300
* _ol_ - http://mysite.org/ImgWorks/imghl/ol/ink/newspapers/bmerchant/03_1871/BM-1871-03-24-03/3000/6000

The _action_ is specified after the _route_ (imghl), and in these examples:

* _site_ - ink
* _collection_ - newspapers
* _reel_ - 03_1871 (reflecting our newspaper focus, a _reel_ is equivalent to a set
* _page_ - BM-1871-03-24-03 (this is the image name)
* _600_ - width (of resulting image)
* _400_ - height (of resulting image)
* _3000_ - width (of source image - OpenLayers only)
* _6000_ - height (of source image - OpenLayers only)
* _town_ - query (not used for _cut_ or _ol_, the terms are extracted and stemmed to determine highlights)

The business logic is contained in 
[module/ImgHl/src/ImgHl/Controller/ImgHlController.php](https://github.com/OurDigitalWorld/imgworks/blob/master/ImgWorks/module/ImgHl/src/ImgHl/Controller/ImgHlController.php). 
The flow is fairly straightforward, it would be worth exploring some
different approaches to identifying the best _cluster_ for showing
highlights on an image but it's a simple count to determine the most stems 
in a region for now.

art rhyno [ourdigitalworld/cdigs](https://github.com/artunit)
