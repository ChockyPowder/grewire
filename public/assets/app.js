const messages=document.querySelector('#messages');
const form=document.querySelector('#message-form');
const input=document.querySelector('#message-input');
const textNav=document.querySelector('#text-channels');
const voiceNav=document.querySelector('#voice-channels');
const channelTitle=document.querySelector('#channel-title');

const chatView=document.querySelector('#chat-view');
const serversView=document.querySelector('#servers-view');
const friendsView=document.querySelector('#friends-view');
const serverList=document.querySelector('#server-list');
const friendList=document.querySelector('#friend-list');
const friendSearch=document.querySelector('#friend-search');
const friendSearchButton=document.querySelector('#friend-search-button');
const friendSearchList=document.querySelector('#friend-search-list');
const friendRequestList=document.querySelector('#friend-request-list');
const pageHeading=document.querySelector('#page-heading');
const pageSubtitle=document.querySelector('#page-subtitle');
const pageAction=document.querySelector('#page-action');

const navChat=document.querySelector('#nav-chat');
const navServers=document.querySelector('#nav-servers');
const navFriends=document.querySelector('#nav-friends');

let channels=[];
let activeChannel=null;
let activeServerId=null;
let refreshTimer=null;

function renderMessages(items){
  messages.innerHTML='';

  if(!items.length){
    messages.innerHTML='<div class="empty-state"><strong>Welcome to the beginning of the chat.</strong><span>No messages here yet.</span></div>';
    return;
  }

  for(const message of items){
    const article=document.createElement('article');
    article.className='message';
    article.innerHTML='<div class="message-avatar"></div><div class="message-content"><div class="message-meta"><strong></strong><time></time></div><div class="message-body"></div></div>';
    article.querySelector('.message-avatar').textContent=(message.author||'U').slice(0,1).toUpperCase();
    article.querySelector('strong').textContent=message.author;
    article.querySelector('time').textContent=new Date(message.created_at).toLocaleString([],{
      month:'short',
      day:'numeric',
      hour:'2-digit',
      minute:'2-digit'
    });
    article.querySelector('.message-body').textContent=message.body;
    messages.appendChild(article);
  }

  messages.scrollTop=messages.scrollHeight;
}

function setNavActive(active){
  [navChat,navServers,navFriends].forEach(link=>link.classList.remove('active'));
  active.classList.add('active');
}

function showChatView(){
  chatView.classList.remove('hidden');
  serversView.classList.add('hidden');
  friendsView.classList.add('hidden');
  pageHeading.textContent='Grewire';
  pageSubtitle.textContent='Your conversations';
  pageAction.textContent='+ New Channel';
  setNavActive(navChat);
}

function showDirectory(view, heading, subtitle, action, nav){
  chatView.classList.add('hidden');
  serversView.classList.add('hidden');
  friendsView.classList.add('hidden');
  view.classList.remove('hidden');
  pageHeading.textContent=heading;
  pageSubtitle.textContent=subtitle;
  pageAction.textContent=action;
  setNavActive(nav);
}

function renderChannels(){
  textNav.innerHTML='';
  voiceNav.innerHTML='';

  for(const channel of channels){
    const button=document.createElement('button');
    button.type='button';
    button.className='channel-button'+(activeChannel?.id===channel.id?' active':'');
    button.dataset.channelId=channel.id;
    button.innerHTML='<span class="channel-icon"></span><span class="channel-name"></span>';
    button.querySelector('.channel-icon').textContent=channel.kind==='voice'?'◉':'#';
    button.querySelector('.channel-name').textContent=channel.name;
    button.addEventListener('click',()=>selectChannel(channel));

    if(channel.kind==='voice') voiceNav.appendChild(button);
    else textNav.appendChild(button);
  }
}

async function loadMessages(){
  if(!activeChannel || activeChannel.kind!=='text') return;

  const response=await fetch('/api/messages.php?channel_id='+encodeURIComponent(activeChannel.id),{
    headers:{Accept:'application/json'},
    cache:'no-store'
  });

  const payload=await response.json();

  if(!response.ok || !payload.ok){
    throw new Error(payload.error||'Unable to load messages');
  }

  renderMessages(payload.messages);
}

async function selectChannel(channel){
  activeChannel=channel;
  channelTitle.textContent=channel.name;

  input.placeholder=channel.kind==='text'
    ? 'Message #'+channel.name+'…'
    : 'Join the voice channel to talk';

  const isText=channel.kind==='text';
  input.disabled=!isText;
  form.querySelector('.composer-emoji').disabled=!isText;

  renderChannels();
  showChatView();

  if(window.setVoiceChannel){
    window.setVoiceChannel(channel);
  }

  if(isText){
    await loadMessages();
  }else{
    messages.innerHTML='<div class="voice-placeholder"><div class="voice-placeholder-icon">◉</div><strong>'+channel.name+'</strong><span>Join the voice channel below to start talking.</span></div>';
  }
}

async function loadServerChannels(serverId){
  const url='/api/channels.php'+(serverId?'?server_id='+encodeURIComponent(serverId):'');
  const response=await fetch(url,{headers:{Accept:'application/json'},cache:'no-store'});
  const payload=await response.json();

  if(!response.ok || !payload.ok){
    throw new Error(payload.error||'Unable to load server');
  }

  activeServerId=payload.workspace.id;
  channels=payload.channels||[];
  if(!channels.length) throw new Error('No channels are available in this server.');

  renderChannels();

  const firstText=channels.find(channel=>channel.kind==='text')||channels[0];
  await selectChannel(firstText);
}

async function loadWorkspace(){
  const response=await fetch('/api/channels.php',{
    headers:{Accept:'application/json'},
    cache:'no-store'
  });

  const payload=await response.json();

  if(!response.ok || !payload.ok){
    throw new Error(payload.error||'Unable to load workspace');
  }

  activeServerId=payload.workspace.id;
  channels=payload.channels||[];
  if(!channels.length) throw new Error('No channels are available.');

  renderChannels();

  const firstText=channels.find(channel=>channel.kind==='text')||channels[0];
  await selectChannel(firstText);
}

async function loadServers(){
  serverList.innerHTML='<div class="directory-empty">Loading servers…</div>';

  const response=await fetch('/api/servers.php',{headers:{Accept:'application/json'},cache:'no-store'});
  const payload=await response.json();

  if(!response.ok || !payload.ok){
    throw new Error(payload.error||'Unable to load servers');
  }

  serverList.innerHTML='';

  if(!payload.servers.length){
    serverList.innerHTML='<div class="directory-empty"><strong>No servers yet</strong><span>You are not in any servers.</span></div>';
    return;
  }

  for(const server of payload.servers){
    const button=document.createElement('button');
    button.type='button';
    button.className='directory-item'+(server.id===activeServerId?' active':'');
    button.innerHTML='<span class="directory-icon"></span><span class="directory-copy"><strong></strong><span>Server</span></span><span class="directory-arrow">›</span>';
    button.querySelector('.directory-icon').textContent=(server.name||'S').slice(0,1).toUpperCase();
    button.querySelector('.directory-copy strong').textContent=server.name;
    button.addEventListener('click',async()=>{
      try{
        await loadServerChannels(server.id);
      }catch(error){
        console.error(error);
        alert(error.message);
      }
    });
    serverList.appendChild(button);
  }
}

async function friendAction(action, data={}){
  const response=await fetch('/api/friends.php',{
    method:'POST',
    headers:{'Content-Type':'application/json',Accept:'application/json'},
    body:JSON.stringify({action,...data})
  });
  const payload=await response.json();
  if(!response.ok || !payload.ok) throw new Error(payload.error||'Friend action failed');
  return payload;
}

function directoryUser(item, actionHtml=''){
  const row=document.createElement('div');
  row.className='directory-item';
  row.innerHTML='<span class="directory-icon"></span><span class="directory-copy"><strong></strong><span></span></span>'+actionHtml;
  row.querySelector('.directory-icon').textContent=(item.display_name||item.username||'U').slice(0,1).toUpperCase();
  row.querySelector('.directory-copy strong').textContent=item.display_name||item.username;
  row.querySelector('.directory-copy span').textContent='@'+item.username;
  return row;
}

async function loadFriends(search=''){
  friendList.innerHTML='<div class="directory-empty">Loading friends…</div>';
  friendRequestList.innerHTML='<div class="directory-empty">Loading requests…</div>';

  const url='/api/friends.php'+(search?'?q='+encodeURIComponent(search):'');
  const response=await fetch(url,{headers:{Accept:'application/json'},cache:'no-store'});
  const payload=await response.json();

  if(!response.ok || !payload.ok) throw new Error(payload.error||'Unable to load friends');

  friendList.innerHTML='';
  friendRequestList.innerHTML='';
  friendSearchList.innerHTML='';

  if(payload.search.length){
    for(const person of payload.search){
      const row=directoryUser(person,'<span class="directory-actions"><button class="btn" type="button">Add Friend</button></span>');
      row.querySelector('button').addEventListener('click',async()=>{
        try{
          await friendAction('send',{user_id:person.id});
          await loadFriends(friendSearch.value.trim());
        }catch(error){ alert(error.message); }
      });
      friendSearchList.appendChild(row);
    }
  }else{
    friendSearchList.innerHTML='<div class="directory-empty"><strong>Search for someone</strong><span>Enter a username or display name above.</span></div>';
  }

  if(payload.incoming.length || payload.outgoing.length){
    for(const request of payload.incoming){
      const row=directoryUser(request,'<span class="directory-actions"><button class="btn btn-primary" type="button">Accept</button><button class="btn" type="button">Decline</button></span>');
      const buttons=row.querySelectorAll('button');
      buttons[0].addEventListener('click',async()=>{try{await friendAction('accept',{request_id:request.id});await loadFriends(friendSearch.value.trim());}catch(error){alert(error.message);}});
      buttons[1].addEventListener('click',async()=>{try{await friendAction('decline',{request_id:request.id});await loadFriends(friendSearch.value.trim());}catch(error){alert(error.message);}});
      friendRequestList.appendChild(row);
    }
    for(const request of payload.outgoing){
      const row=directoryUser(request,'<span class="directory-actions"><button class="btn" type="button">Cancel</button></span>');
      row.querySelector('button').addEventListener('click',async()=>{try{await friendAction('cancel',{request_id:request.id});await loadFriends(friendSearch.value.trim());}catch(error){alert(error.message);}});
      friendRequestList.appendChild(row);
    }
  }else{
    friendRequestList.innerHTML='<div class="directory-empty"><strong>No pending requests</strong><span>Incoming and outgoing requests will appear here.</span></div>';
  }

  if(payload.friends.length){
    for(const friend of payload.friends){
      const row=directoryUser(friend,'<span class="directory-actions"><button class="btn" type="button">Remove</button></span>');
      row.querySelector('button').addEventListener('click',async()=>{
        if(!confirm('Remove this friend?')) return;
        try{await friendAction('remove',{user_id:friend.id});await loadFriends(friendSearch.value.trim());}catch(error){alert(error.message);}
      });
      friendList.appendChild(row);
    }
  }else{
    friendList.innerHTML='<div class="directory-empty"><strong>No friends added yet</strong><span>Search for someone above to send a friend request.</span></div>';
  }
}

friendSearchButton?.addEventListener('click',async()=>{
  try{ await loadFriends(friendSearch.value.trim()); }
  catch(error){ alert(error.message); }
});

friendSearch?.addEventListener('keydown',event=>{
  if(event.key==='Enter'){
    event.preventDefault();
    friendSearchButton?.click();
  }
});

navChat.addEventListener('click',async event=>{
  event.preventDefault();
  showChatView();
  if(activeChannel) await selectChannel(activeChannel);
});

navServers.addEventListener('click',async event=>{
  event.preventDefault();
  showDirectory(serversView,'Servers','Servers you are in','+ Create Server',navServers);
  try{ await loadServers(); }
  catch(error){ serverList.innerHTML='<div class="directory-empty"><strong>Unable to load servers</strong><span>'+String(error.message||error)+'</span></div>'; }
});

navFriends.addEventListener('click',async event=>{
  event.preventDefault();
  showDirectory(friendsView,'Friends','Your friends and friend requests','+ Add Friend',navFriends);
  try{ await loadFriends(); }
  catch(error){ friendList.innerHTML='<div class="directory-empty"><strong>Unable to load friends</strong><span>'+String(error.message||error)+'</span></div>'; }
});

form.addEventListener('submit',async event=>{
  event.preventDefault();

  if(!activeChannel || activeChannel.kind!=='text') return;

  const body=input.value.trim();
  if(!body) return;

  input.disabled=true;

  try{
    const response=await fetch('/api/messages.php?channel_id='+encodeURIComponent(activeChannel.id),{
      method:'POST',
      headers:{'Content-Type':'application/json',Accept:'application/json'},
      body:JSON.stringify({body})
    });

    const payload=await response.json();

    if(!response.ok || !payload.ok){
      throw new Error(payload.error||'Unable to send message');
    }

    input.value='';
    await loadMessages();
  }catch(error){
    console.error(error);
    alert(error.message);
  }finally{
    input.disabled=false;
    input.focus();
  }
});

async function start(){
  try{
    await loadWorkspace();

    if(refreshTimer) clearInterval(refreshTimer);

    refreshTimer=setInterval(async()=>{
      try{
        await loadMessages();
      }catch(error){
        console.warn('Message refresh failed:',error);
      }
    },3000);
  }catch(error){
    console.error('Grewire startup failed:',error);
    messages.innerHTML='<div class="startup-error"><strong>Unable to connect to Grewire</strong><span>'+String(error.message||error)+'</span><button type="button" onclick="location.reload()">Retry</button></div>';
  }
}

start();
