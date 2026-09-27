const voiceButton=document.querySelector('#voice-join');
const voicePanel=document.querySelector('#voice-panel');
const voiceStatus=document.querySelector('#voice-status');
const voiceParticipants=document.querySelector('#voice-participants');

let voiceSocket=null;
let localStream=null;
let voiceChannel=null;
let myPeerId=null;
const peers=new Map();
const pendingCandidates=new Map();

function voiceSetStatus(text){ if(voiceStatus) voiceStatus.textContent=text; }

function voiceWsUrl(){
  const scheme=location.protocol==='https:'?'wss':'ws';
  return scheme+'://'+location.host+'/ws/';
}

function participantEl(peerId,name,local=false){
  const row=document.createElement('div');
  row.className='voice-participant';
  row.dataset.peerId=peerId;
  row.innerHTML='<span class="voice-dot"></span><span></span>'+ (local?'<span class="voice-you">YOU</span>':'');
  row.querySelector('span:nth-child(2)').textContent=name||'User';
  return row;
}

function renderVoiceParticipants(){
  voiceParticipants.innerHTML='';
  if(localStream && myPeerId){
    voiceParticipants.appendChild(participantEl(myPeerId,'local-user',true));
  }
  for(const [peerId,peer] of peers){
    voiceParticipants.appendChild(participantEl(peerId,peer.name||'User'));
  }
}

function addRemoteAudio(peerId,stream){
  let audio=document.querySelector('audio[data-peer-id="'+CSS.escape(peerId)+'"]');
  if(!audio){
    audio=document.createElement('audio');
    audio.autoplay=true;
    audio.playsInline=true;
    audio.dataset.peerId=peerId;
    voicePanel.appendChild(audio);
  }
  audio.srcObject=stream;
}

function removePeer(peerId){
  const peer=peers.get(peerId);
  if(peer?.pc) peer.pc.close();
  peers.delete(peerId);
  pendingCandidates.delete(peerId);
  document.querySelector('audio[data-peer-id="'+CSS.escape(peerId)+'"]')?.remove();
  renderVoiceParticipants();
}

async function flushCandidates(peerId){
  const peer=peers.get(peerId);
  const queued=pendingCandidates.get(peerId)||[];
  if(!peer?.pc.remoteDescription) return;
  for(const candidate of queued){
    try{ await peer.pc.addIceCandidate(candidate); }catch(error){ console.warn('ICE candidate failed',error); }
  }
  pendingCandidates.delete(peerId);
}

function sendVoice(message){
  if(voiceSocket?.readyState===WebSocket.OPEN){
    voiceSocket.send(JSON.stringify(message));
  }
}

async function createPeer(peerId,name,initiator=false){
  if(peers.has(peerId)) return peers.get(peerId);

  const pc=new RTCPeerConnection({
    iceServers:[]
  });

  for(const track of localStream.getTracks()){
    pc.addTrack(track,localStream);
  }

  const peer={pc,name};
  peers.set(peerId,peer);
  renderVoiceParticipants();

  pc.onicecandidate=event=>{
    if(event.candidate){
      sendVoice({type:'candidate',target:Number(peerId),candidate:event.candidate});
    }
  };

  pc.ontrack=event=>{
    if(event.streams[0]) addRemoteAudio(peerId,event.streams[0]);
  };

  pc.onconnectionstatechange=()=>{
    if(['failed','closed','disconnected'].includes(pc.connectionState)){
      if(pc.connectionState!=='disconnected') removePeer(peerId);
    }
  };

  if(initiator){
    const offer=await pc.createOffer();
    await pc.setLocalDescription(offer);
    sendVoice({type:'offer',target:Number(peerId),description:pc.localDescription});
  }

  return peer;
}

async function handleVoiceSignal(message){
  if(message.type==='connected'){
    myPeerId=message.peerId;
    return;
  }

  if(message.type==='room-joined'){
    voiceSetStatus('Connected · '+message.participants+' participant'+(message.participants===1?'':'s'));
    return;
  }

  if(message.type==='existing-peer'){
    await createPeer(message.peerId,message.name,true);
    return;
  }

  if(message.type==='peer-joined'){
    await createPeer(message.peerId,message.name,false);
    return;
  }

  if(message.type==='peer-left'){
    removePeer(message.peerId);
    return;
  }

  if(message.type==='offer'){
    const peer=await createPeer(message.from,'User',false);
    await peer.pc.setRemoteDescription(message.description);
    await flushCandidates(message.from);
    const answer=await peer.pc.createAnswer();
    await peer.pc.setLocalDescription(answer);
    sendVoice({type:'answer',target:Number(message.from),description:peer.pc.localDescription});
    return;
  }

  if(message.type==='answer'){
    const peer=peers.get(message.from);
    if(!peer) return;
    await peer.pc.setRemoteDescription(message.description);
    await flushCandidates(message.from);
    return;
  }

  if(message.type==='candidate'){
    const peer=peers.get(message.from);
    const candidate=new RTCIceCandidate(message.candidate);
    if(peer?.pc.remoteDescription){
      try{ await peer.pc.addIceCandidate(candidate); }catch(error){ console.warn('ICE candidate failed',error); }
    }else{
      pendingCandidates.set(message.from,[...(pendingCandidates.get(message.from)||[]),candidate]);
    }
  }
}

async function joinVoice(){
  if(voiceChannel?.kind!=='voice') return;

  if(!window.isSecureContext || !navigator.mediaDevices?.getUserMedia){
    voiceSetStatus('Voice requires HTTPS on this LAN address.');
    alert('Microphone access requires HTTPS. Run: bash scripts/setup-voice-lan.sh on the server, then open the HTTPS address.');
    return;
  }

  voiceButton.disabled=true;
  voiceSetStatus('Requesting microphone…');

  try{
    localStream=await navigator.mediaDevices.getUserMedia({audio:true,video:false});
    voiceSocket=new WebSocket(voiceWsUrl());

    voiceSocket.onopen=()=>{
      sendVoice({type:'join',room:voiceChannel.id,name:'local-user'});
    };

    voiceSocket.onmessage=async event=>{
      try{ await handleVoiceSignal(JSON.parse(event.data)); }
      catch(error){ console.error('Voice signaling error',error); voiceSetStatus('Voice error'); }
    };

    voiceSocket.onerror=()=>{
      voiceSetStatus('Signaling server unavailable');
    };

    voiceSocket.onclose=()=>{
      voiceSetStatus('Disconnected');
      for(const peerId of [...peers.keys()]) removePeer(peerId);
      myPeerId=null;
      renderVoiceParticipants();
      if(localStream){
        for(const track of localStream.getTracks()) track.stop();
        localStream=null;
      }
      voiceButton.disabled=false;
      voiceButton.textContent='Join voice';
    };

    voiceButton.textContent='Leave voice';
    voiceButton.disabled=false;
    voiceButton.dataset.joined='true';
    voicePanel.classList.add('joined');
    voiceSetStatus('Connecting…');
  }catch(error){
    console.error(error);
    voiceSetStatus('Microphone permission failed');
    voiceButton.disabled=false;
  }
}

function leaveVoice(){
  sendVoice({type:'leave'});
  voiceSocket?.close();
}

voiceButton?.addEventListener('click',()=>{
  if(voiceButton.dataset.joined==='true') leaveVoice();
  else joinVoice();
});

window.setVoiceChannel=channel=>{
  voiceChannel=channel;
  voicePanel.classList.toggle('hidden',channel?.kind!=='voice');
  voicePanel.classList.toggle('joined',false);
  if(channel?.kind==='voice'){
    voiceSetStatus('Not connected');
    voiceButton.dataset.joined='false';
    voiceButton.textContent='Join voice';
    voiceButton.disabled=false;
    if(voiceSocket) leaveVoice();
  }
};

window.addEventListener('beforeunload',()=>{ try{ sendVoice({type:'leave'}); }catch{} });
renderVoiceParticipants();
