function getSubPages(){
  urls = document.querySelectorAll('a');
  var urlStorage = new Array();
  var temp;
  for (url in urls) {
    if(urls[url].href != null && urls[url].href.includes('https://webnique.de')){
      urlStorage.push(urls[url].href);
    }
  }
  
  
  //var urlString = urlStorage.join('\n')
  //downloadVariable( urlString,'pageURLS.txt');
  return urlStorage;
}

return getSubPages();