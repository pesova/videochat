

<template>
    <Head title="Video Chat" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Video Chat
            </h2>
        </template>


        <div>
            <div class="container">
            <div class="row">
                <div class="col">
                <div class="btn-group" role="group">
                    <button
                    type="button"
                    class="btn btn-primary mr-2"
                    v-for="user in allusers"
                    :key="user.id"
                    @click="placeVideoCall(user.id, user.name)"
                    >
                    Call {{ user.name }}
                    <span class="badge badge-light">{{
                        getUserOnlineStatus(user.id)
                    }}</span>
                    </button>
                </div>
                </div>
            </div>
            <!--Placing Video Call-->
            <div class="row mt-5" id="video-row">
                <div class="col-12 video-container" v-if="callPlaced">
                <video
                    ref="userVideo"
                    muted
                    playsinline
                    autoplay
                    class="cursor-pointer"
                    :class="isFocusMyself === true ? 'user-video' : 'partner-video'"
                    @click="toggleCameraArea"
                />
                <video
                    ref="partnerVideo"
                    playsinline
                    autoplay
                    class="cursor-pointer"
                    :class="isFocusMyself === true ? 'partner-video' : 'user-video'"
                    @click="toggleCameraArea"
                    v-if="videoCallParams.callAccepted"
                />
                <div class="partner-video" v-else>
                    <div v-if="callPartner" class="column items-center q-pt-xl">
                    <div class="col q-gutter-y-md text-center">
                        <p class="q-pt-md">
                        <strong>{{ callPartner }}</strong>
                        </p>
                        <p>calling...</p>
                    </div>
                    </div>
                </div>
                <div class="action-btns">
                    <button type="button" class="btn btn-info" @click="toggleMuteAudio">
                    {{ mutedAudio ? "Unmute" : "Mute" }}
                    </button>
                    <button
                    type="button"
                    class="btn btn-primary mx-4"
                    @click="toggleMuteVideo"
                    >
                    {{ mutedVideo ? "ShowVideo" : "HideVideo" }}
                    </button>
                    <button type="button" class="btn btn-danger" @click="endCall">
                    EndCall
                    </button>
                </div>
                </div>
            </div>
            <!-- End of Placing Video Call  -->

            <!-- Incoming Call  -->
            <div class="row" v-if="incomingCallDialog">
                <div class="col">
                <p>
                    Incoming Call From <strong>{{ callerDetails.name }}</strong>
                </p>
                <div class="btn-group" role="group">
                    <button
                    type="button"
                    class="btn btn-danger"
                    data-dismiss="modal"
                    @click="declineCall"
                    >
                    Decline
                    </button>
                    <button
                    type="button"
                    class="btn btn-success ml-5"
                    @click="acceptCall"
                    >
                    Accept
                    </button>
                </div>
                </div>
            </div>
            <!-- End of Incoming Call  -->
            </div>
        </div>
    </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head } from '@inertiajs/inertia-vue3';
import { onMounted, ref } from 'vue';
// import Peer from 'simple-peer'

// import { VueWebRTC } from 'vue-webrtc'
// // Vue.component(VueWebRTC.name, VueWebRTC)

// app.component('VueWebRTC', VueWebRTC)

// const onConnection = (socket) => {
//     socket.on('joinRoom', events.joinRoom(socket, namespace)) // Join a room
//     socket.on('publicMessage', events.publicMessage(namespace)) // New public messages
//     socket.on('leaveRoom', events.leaveRoom(socket, namespace)) // Leave room
//     socket.on('leaveChat', events.leaveChat(socket, namespace)) // Leave the chat
//     socket.on('joinPrivateRoom', events.joinPrivateRoom(socket, namespace)) // Join private chat
//     socket.on('leavePrivateRoom', events.leavePrivateRoom(socket, namespace)) // Leave private chat
//     socket.on('privateMessage', events.privateMessage(namespace)) // Private message
//     socket.on('changeStatus', events.changeStatus(socket, namespace)) // // Set status
// }

const video_src = ref('');
const user1Stream = ref();
const user_id = document.querySelector("meta[name='user_id']").getAttribute('content');

const props = defineProps(['allusers']);



// props
let authuserid = ref();
let turn_url = ref();
let turn_username = ref();
let turn_credential = ref();

let userVideo = ref();
let partnerVideo = ref();


//data
let isFocusMyself = ref(true)
let callPlaced = ref(false)
let callPartner = ref()
let mutedAudio = ref(false)
let mutedVideo = ref(false)

let videoCallParams = ref({
    users: [],
    stream: null,
    receivingCall: false,
    caller: null,
    callerSignal: null,
    callAccepted: false,
    channel: null,
    peer1: null,
    peer2: null,
})


const incomingCallDialog = () =>  {
    return videoCallParams.value.receivingCall &&
    videoCallParams.value.caller !== authuserid
}


const callerDetails = () => {
    if (
        videoCallParams.value.caller &&
        videoCallParams.value.caller !== this.authuserid
    ) {
        const incomingCaller = this.allusers.filter(
            (user) => user.id === videoCallParams.value.caller
        );
        return {
            id: videoCallParams.value.caller,
            name: `${incomingCaller[0].name}`,
        };
    }
    return null;
}

const initializeChannel = () => {
    videoCallParams.value.channel = window.Echo.join("presence-video-channel");
}

const getMediaPermission = () => {
    navigator.mediaDevices.getUserMedia(  {video: true}).then((stream) => {
        videoCallParams.value.stream = stream;
        if (userVideo) {
            userVideo.value.srcObject = stream;
        }
      })
      .catch((error) => {
        console.log(error);
    });
}

const initializeCallListeners = () => {
    videoCallParams.value.channel.here((users) => {
        videoCallParams.value.users = users;
    });

    videoCallParams.value.channel.joining((user) => {
        // check user availability
        const joiningUserIndex = videoCallParams.value.users.findIndex(
            (data) => data.id === user.id
        );
        if (joiningUserIndex < 0) {
            videoCallParams.value.users.push(user);
        }
    });

    // leave call
    videoCallParams.value.channel.leaving((user) => {
        const leavingUserIndex = videoCallParams.value.users.findIndex(
            (data) => data.id === user.id
        );
        videoCallParams.value.users.splice(leavingUserIndex, 1);
    });

    // listen to incomming call
    videoCallParams.value.channel.listen("StartVideoChat", ({ data }) => {
        if (data.type === "incomingCall") {
            // add a new line to the sdp to take care of error
            const updatedSignal = {
            ...data.signalData,
            sdp: `${data.signalData.sdp}\n`,
            };
            videoCallParams.value.receivingCall = true;
            videoCallParams.value.caller = data.from;
            videoCallParams.value.callerSignal = updatedSignal;
        }
    });
}


const placeVideoCall = async (id, name) => {
    callPlaced.value = true;
    callPartner.value = name;
    await getMediaPermission();

    videoCallParams.value.peer1 = new SimplePeer({
        initiator: true,
        trickle: false,
        stream: videoCallParams.value.stream,
        config: {
            iceServers: [
                {
                    urls: turn_url,
                    username: turn_username,
                    credential: turn_credential,
                },
            ],
        },
    });

    videoCallParams.value.peer1.on("signal", (data) => {
    // send user call signal
    axios
        .post("/video/call-user", {
            user_to_call: id,
            signal_data: data,
            from: this.authuserid,
        })
        .then(() => {})
        .catch((error) => {
            console.log(error);
        });
    });

    videoCallParams.value.peer1.on("stream", (stream) => {
        console.log("call streaming");
        if (partnerVideo) {
            partnerVideo.value.srcObject = stream;
        }
    });

    videoCallParams.value.peer1.on("connect", () => {
        console.log("peer connected");
    });

    videoCallParams.value.peer1.on("error", (err) => {
        console.log(err);
    });

    videoCallParams.value.peer1.on("close", () => {
        console.log("call closed caller");
    });

    videoCallParams.value.channel.listen("StartVideoChat", ({ data }) => {
        if (data.type === "callAccepted") {
            if (data.signal.renegotiate) {
            console.log("renegotating");
            }
            if (data.signal.sdp) {
            videoCallParams.value.callAccepted = true;
            const updatedSignal = {
                ...data.signal,
                sdp: `${data.signal.sdp}\n`,
            };
            videoCallParams.value.peer1.signal(updatedSignal);
            }
        }
    });
}

const acceptCall = async () => {
    callPlaced.value = true;
    videoCallParams.value.callAccepted = true;
    await getMediaPermission();
    videoCallParams.value.peer2 = new SimplePeer({
    initiator: false,
    trickle: false,
    stream: videoCallParams.value.stream,
    config: {
        iceServers: [
            {
                urls: turn_url,
                username: turn_username,
                credential: turn_credential,
            },
        ],
    },
    });

    videoCallParams.value.receivingCall = false;
    videoCallParams.value.peer2.on("signal", (data) => {
        axios
            .post("/video/accept-call", {
            signal: data,
            to: videoCallParams.value.caller,
            })
            .then(() => {})
            .catch((error) => {
            console.log(error);
        });
    });

    videoCallParams.value.peer2.on("stream", (stream) => {
        videoCallParams.value.callAccepted = true;
        partnerVideo.value.srcObject = stream;
    });

    videoCallParams.value.peer2.on("connect", () => {
        console.log("peer connected");
        videoCallParams.value.callAccepted = true;
    });

    videoCallParams.value.peer2.on("error", (err) => {
        console.log(err);
    });

    videoCallParams.value.peer2.on("close", () => {
        console.log("call closed accepter");
    });

    videoCallParams.value.peer2.signal(videoCallParams.value.callerSignal);
}

const toggleCameraArea = () => {
    if (videoCallParams.value.callAccepted) {
    this.isFocusMyself = !this.isFocusMyself;
    }
}


const getUserOnlineStatus = (id) => {
    const onlineUserIndex = videoCallParams.value.users.findIndex(
        (data) => data.id === id
    );
    if (onlineUserIndex < 0) {
        return "Offline";
    }

    return "Online";
}


const declineCall = () => {
    videoCallParams.value.receivingCall = false;
}


const toggleMuteAudio = () => {
    if (mutedAudio) {
        userVideo.value.srcObject.getAudioTracks()[0].enabled = true;
        mutedAudio.value = false;
    } else {
        userVideo.value.srcObject.getAudioTracks()[0].enabled = false;
        mutedAudio.value = true;
    }
}


const toggleMuteVideo = () => {
    if (mutedVideo) {
        userVideo.value.srcObject.getVideoTracks()[0].enabled = true;
        mutedVideo.value = false;
    } else {
        userVideo.value.srcObject.getVideoTracks()[0].enabled = false;
        mutedVideo.value = true;
    }
}


const stopStreamedVideo = (videoElem) => {
    const stream = videoElem.srcObject;
    const tracks = stream.getTracks();
    tracks.forEach((track) => {
        track.stop();
    });
    videoElem.srcObject = null;
}


const endCall = () => {
    // if video or audio is muted, enable it so that the stopStreamedVideo method will work
    if (!mutedVideo) toggleMuteVideo();
    if (!mutedAudio) toggleMuteAudio();

    stopStreamedVideo(userVideo);
    if (this.authuserid === videoCallParams.value.caller) {
        videoCallParams.value.peer1.destroy();
    } else {
        videoCallParams.value.peer2.destroy();
    }

    videoCallParams.value.channel.pusher.channels.channels[
    "presence-presence-video-channel"
    ].disconnect();
    setTimeout(() => {
        callPlaced.value = false;
    }, 3000);
}



// const startPeer = (userStream ) => {
//     let peer1 = new Peer({ initiator: true, stream: user1Stream, trickle: false }) 
//     peer1.on('signal', (data) => {
//         console.log(data)
//     })
    
// }


const myVideo = ref();


const test = (e) => {

    const fireevent = fetch('/fire').then((res) => {
        console.log(res)
    })
}

const getMedia = async () => {

      navigator.mediaDevices.getUserMedia(  {video: true}).then((stream) => {

        user1Stream.value = stream
          
        myVideo.value.srcObject = stream;
        myVideo.value.setAttribute('autoplay', '');

      });


    // // video.setAttribute('playsinline', '');
    // video.setAttribute('autoplay', '');
    // // video.setAttribute('muted', '');
    // // video.style.width = '200px';
    // // video.style.height = '200px';

   
}


onMounted(() => {

    // let peer2 = new SimplePeer({ initiator: true })

    // Echo.join('my-channel', (data) => {
    //     console.log(data)
    // })
    
    // peer2.on('my-channel', (data) => {
    //     console.log(data)
    // })

    // const socket = new WebSocket('localhost')

    // console.log("Starting connection to WebSocket Server")
    // this.connection = new WebSocket("wss://echo.websocket.org")

    // this.connection.onmessage = function(event) {
    //   console.log(event);
    // }
    // this.connection.onopen = function(event) {
    //   console.log(event)
    //   console.log("Successfully connected to the echo websocket server...")
    // }

    // getMedia();




})

// const handleChat = () => {
//         Echo.channel(`chat_${user_id}`)
//         .listen('TestEvent', (e) => {
//             console.log(e);
//             console.log('read without refresh');
//         });
// }


</script>
