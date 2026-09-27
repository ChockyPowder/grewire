const status=document.querySelector('#connection-status');
const messages=document.querySelector('#messages');
const form=document.querySelector('#message-form');
const input=document.querySelector('#message-input');
const nav=document.querySelector('nav');
let channels=[];
let activeChannel=null;
let refreshTimer=null;

function escapeHtml(value){
  const div=document.createElement('div');
  div.textContent=String(value ?? '');
  return div.innerHTML;
}

function setStatus(state,text){
  status.dataset.state=state;
  status.querySelector('span:last-child').textContent=text;
}

function renderMessages(items){
  messages.innerHTML='';
  if(!items.length){
    messages.innerHTML='<div class="empty-state">No messages yet. Say hello.</div>';
    return;
  }

  for(const message of items){
    const article=document.createElement('article');
    article.className='message';
    article.innerHTML='<div class="avatar">L</div><div><strong></strong><time></time><p></p></div>';
    article.querySelector('strong').textContent=message.author;
    article.querySelector('time').textContent=new Date(message.created_at).toLocaleTimeString([],{
      hour:'2-digit',
      minute:'2-digit'
    });
    article.querySelector('p').textContent=message.body;
    messages.appendChild(article);
  }

  messages.scrollTop=messages.scrollHeight;
}

function renderChannels(){
  nav.innerHTML='';

  for(const channel of channels){
    const button=document.createElement('button');
    button.type='button';
    button.className='channel'+(activeChannel?.id===channel.id?' active':'');
    button.dataset.channelId=channel.id;
    button.textContent=(channel.kind==='voice'?'◉ ':'# ')+channel.name;
    button.addEventListener('click',()=>selectChannel(channel));
    nav.appendChild(button);
  }
}

async function loadMessages(){
  if(!activeChannel) return;

  const response=await fetch('/api/messages.php?channel_id='+encodeURIComponent(activeChannel.id),{
    headers:{Accept:'application/json'},
    cache:'no-store'
  });
  const payload=await response.json();

  if(!response.ok || !payload.ok){
    throw new Error(payload.error || 'Unable to load messages');
  }

  renderMessages(payload.messages);
}

async function selectChannel(channel){
  activeChannel=channel;
  document.querySelector('.channel-title').textContent=
    (channel.kind==='voice'?'◉ ':'# ')+channel.name;

  document.querySelector('.chat-header p').textContent=
    channel.kind==='voice'
      ? 'Voice channel · voice/WebRTC coming next'
      : 'Early-stage Grewire workspace';

  input.placeholder=channel.kind==='text'
    ? 'Message #'+channel.name+'…'
    : 'Voice channel — messaging disabled';

  input.disabled=channel.kind!=='text';
  form.querySelector('button').disabled=channel.kind!=='text';

  renderChannels();

  try{
    await loadMessages();
    setStatus('ok','PostgreSQL connected');
  }catch(error){
    setStatus('error','Unable to load channel');
    console.error(error);
  }
}

async function loadWorkspace(){
  const response=await fetch('/api/channels.php',{
    headers:{Accept:'application/json'},
    cache:'no-store'
  });
  const payload=await response.json();

  if(!response.ok || !payload.ok){
    throw new Error(payload.error || 'Unable to load workspace');
  }

  channels=payload.channels;
  renderChannels();

  const firstText=channels.find(channel=>channel.kind==='text') || channels[0];
  await selectChannel(firstText);
}

form.addEventListener('submit',async event=>{
  event.preventDefault();

  if(!activeChannel || activeChannel.kind!=='text') return;

  const body=input.value.trim();
  if(!body) return;

  input.disabled=true;
  form.querySelector('button').disabled=true;

  try{
    const response=await fetch(
      '/api/messages.php?channel_id='+encodeURIComponent(activeChannel.id),
      {
        method:'POST',
        headers:{
          'Content-Type':'application/json',
          Accept:'application/json'
        },
        body:JSON.stringify({body})
      }
    );

    const payload=await response.json();

    if(!response.ok || !payload.ok){
      throw new Error(payload.error || 'Unable to send message');
    }

    input.value='';
    await loadMessages();
    setStatus('ok','PostgreSQL connected');
  }catch(error){
    setStatus('error','Message failed');
    console.error(error);
    alert(error.message);
  }finally{
    input.disabled=false;
    form.querySelector('button').disabled=false;
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
        setStatus('ok','PostgreSQL connected');
      }catch(error){
        setStatus('error','PostgreSQL unavailable');
      }
    },3000);
  }catch(error){
    setStatus('error','Grewire unavailable');
    console.error(error);
  }
}

start();
