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

async function loadFriends(){
  friendList.innerHTML='<div class="directory-empty">Loading friends…</div>';

  const response=await fetch('/api/friends.php',{headers:{Accept:'application/json'},cache:'no-store'});
  const payload=await response.json();

  if(!response.ok || !payload.ok){
    throw new Error(payload.error||'Unable to load friends');
  }

  friendList.innerHTML='';

  if(!payload.friends.length){
    friendList.innerHTML='<div class="directory-empty"><strong>No friends added yet</strong><span>Your added friends will appear here.</span></div>';
    return;
  }

  for(const friend of payload.friends){
    const item=document.createElement('div');
    item.className='directory-item';
    item.innerHTML='<span class="directory-icon"></span><span class="directory-copy"><strong></strong><span class="friend-username"></span></span>';
    item.querySelector('.directory-icon').textContent=(friend.display_name||friend.username||'F').slice(0,1).toUpperCase();
    item.querySelector('.directory-copy strong').textContent=friend.display_name||friend.username;
    item.querySelector('.friend-username').textContent='@'+friend.username;
    friendList.appendChild(item);
  }
}

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
  showDirectory(friendsView,'Friends','Your added friends','+ Add Friend',navFriends);
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
