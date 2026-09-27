const messages=document.querySelector('#messages');
const form=document.querySelector('#message-form');
const input=document.querySelector('#message-input');
const textNav=document.querySelector('#text-channels');
const voiceNav=document.querySelector('#voice-channels');
const channelTitle=document.querySelector('#channel-title');

let channels=[];
let activeChannel=null;
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
  updateMessageCount(items.length);
}

function updateMessageCount(count){
  document.title=(activeChannel?('# '+activeChannel.name+' · '):'')+'Grewire';
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

  if(window.setVoiceChannel){
    window.setVoiceChannel(channel);
  }

  if(isText){
    await loadMessages();
  }else{
    messages.innerHTML='<div class="voice-placeholder"><div class="voice-placeholder-icon">◉</div><strong>'+channel.name+'</strong><span>Join the voice channel below to start talking.</span></div>';
  }
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

  channels=payload.channels||[];
  if(!channels.length) throw new Error('No channels are available.');

  renderChannels();

  const firstText=channels.find(channel=>channel.kind==='text')||channels[0];
  await selectChannel(firstText);
}

form.addEventListener('submit',async event=>{
  event.preventDefault();

  if(!activeChannel || activeChannel.kind!=='text') return;

  const body=input.value.trim();
  if(!body) return;

  const sendButton=form.querySelector('.composer-add');
  input.disabled=true;

  try{
    const response=await fetch('/api/messages.php?channel_id='+encodeURIComponent(activeChannel.id),{
      method:'POST',
      headers:{
        'Content-Type':'application/json',
        Accept:'application/json'
      },
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
    void sendButton;
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
