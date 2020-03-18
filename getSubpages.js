
function getSubpages(hostcode){
  
  const urls = document.getElementsByTagName('a')
  
  for(let i = 0; i < urls.length; i++) {
  
  if (urls[i].length > 0  && urls[i].href.includes(hostcode)) {
  
    
    urlStorage.push(urls[i].href)
  }
  
}
  return urlStorage;
}

