

<template>
    <Head title="Video Chat" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Video Chat
            </h2>
        </template>

        <div>
            <div class="container mx-auto sm:px-4">
            <div class="flex flex-wrap ">
                <div class="relative flex-grow max-w-full flex-1 px-4">
                <div class="relative inline-flex align-middle" role="group">
                    <button
                    type="button"
                    class="inline-block align-middle text-center select-none border font-normal whitespace-no-wrap rounded py-1 px-3 leading-normal no-underline bg-blue-600 text-white hover:bg-blue-600 mr-2"
                    v-for="user in allusers"
                    :key="user.id"
                    @click="placeVideoCall(user.id, user.name)"
                    >
                    Call {{ user.name }}
                    <span class="inline-block p-1 text-center font-semibold text-sm align-baseline leading-none rounded bg-gray-100 text-gray-800 hover:bg-gray-200">{{
                        getUserOnlineStatus(user.id)
                    }}</span>
                    </button>
                </div>
                </div>
            </div>
            <!--Placing Video Call-->
            <div class="flex flex-wrap  mt-5" id="video-row">
                <div class="w-full video-container" v-if="callPlaced">
                <video
                    ref="userVideo"
                    muted
                    class="cursor-pointer"
                    :class="isFocusMyself === true ? 'user-video' : 'partner-video'"
                    @click="toggleCameraArea"
                />
                <video
                    ref="partnerVideo"
                    class="cursor-pointer"
                    :class="isFocusMyself === true ? 'partner-video' : 'user-video'"
                    @click="toggleCameraArea"
                    v-if="videoCallParams.callAccepted"
                />
                <div class="partner-video" v-else>
                    <div v-if="callPartner" class="column items-center q-pt-xl">
                    <div class="relative flex-grow max-w-full flex-1 px-4 q-gutter-y-md text-center">
                        <p class="q-pt-md">
                        <strong>{{ callPartner }}</strong>
                        </p>
                        <p>calling...</p>
                    </div>
                    </div>
                </div>
                <div class="action-btns">
                    <button type="button" class="inline-block align-middle text-center select-none border font-normal whitespace-no-wrap rounded py-1 px-3 leading-normal no-underline bg-teal-500 text-white hover:bg-teal-600" @click="toggleMuteAudio">
                    {{ mutedAudio ? "Unmute" : "Mute" }}
                    </button>
                    <button
                    type="button"
                    class="inline-block align-middle text-center select-none border font-normal whitespace-no-wrap rounded py-1 px-3 leading-normal no-underline bg-blue-600 text-white hover:bg-blue-600 mx-4"
                    @click="toggleMuteVideo"
                    >
                    {{ mutedVideo ? "ShowVideo" : "HideVideo" }}
                    </button>
                    <button type="button" class="inline-block align-middle text-center select-none border font-normal whitespace-no-wrap rounded py-1 px-3 leading-normal no-underline bg-red-600 text-white hover:bg-red-700" @click="endCall">
                    EndCall
                    </button>
                </div>
                </div>
            </div>
            <!-- End of Placing Video Call  -->

            <!-- Incoming Call  -->
            <div class="flex flex-wrap " v-if="incomingCallDialog()">
                <div class="relative flex-grow max-w-full flex-1 px-4">
                <p>
                    Incoming Call From <strong>{{ callerDetails().name }}</strong>
                </p>
                <div class="relative inline-flex align-middle" role="group">
                    <button
                    type="button"
                    class="inline-block align-middle text-center select-none border font-normal whitespace-no-wrap rounded py-1 px-3 leading-normal no-underline bg-red-600 text-white hover:bg-red-700"
                    data-dismiss="modal"
                    @click="declineCall"
                    >
                    Decline
                    </button>
                    <button
                    type="button"
                    class="inline-block align-middle text-center select-none border font-normal whitespace-no-wrap rounded py-1 px-3 leading-normal no-underline bg-green-500 text-white hover:bg-green-600 ml-5"
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
import io from 'socket.io-client';
// import Peer from 'simple-peer'


// const socket = io('ws://127.0.0.1:5173')
// console.log(socket)
// const peer = new RTCPeerConnection({
//             iceServers: [
//                 {
//                     urls: "stun:stun.stunprotocol.org"
//                 },
//                 {
//                     urls: 'turn:numb.viagenie.ca',
//                     credential: 'muazkh',
//                     username: 'webrtc@live.com'
//                 },
//             ]
//         });

//         console.log(peer);
const user1Stream = ref();
const user_id = ref();


const props = defineProps(['allusers']);
let authuserid = user_id;

let streamvideo = ref();

let peerUser1 = ref();
let peerUser2 = ref();



onMounted(() => {
    console.log('mounted')
    user_id.value = document.querySelector("meta[name='user_id']").getAttribute('content');
    
    initializeChannel(); // this initializes laravel echo
    initializeCallListeners();
    getMediaPermission();

    // let peer2 = new SimplePeer({ initiator: true })

    // Echo.join('my-channel', (data) => {
    //     console.log(data)
    // })
    
    // peer2.on('my-channel', (data) => {
    //     console.log(data)
    // })

    // const socket = new WebSocket('localhost')


    // getMedia();

})
let userVideo = ref();
let partnerVideo = ref();

console.log({userVideo});





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
    return !!(videoCallParams.value.receivingCall &&
    videoCallParams.value.caller !== authuserid);
}


const callerDetails = () => {
    if (
        videoCallParams.value.caller &&
        videoCallParams.value.caller !== authuserid
    ) {
        const incomingCaller = props.allusers.filter(
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
    navigator.mediaDevices.getUserMedia({ video: true, audio: true }).then((stream) => {
        streamvideo.value = stream;
        // userVideo.value.srcObject = stream;
        // userVideo.value.setAttribute('autoplay', '');
      })
      .catch((error) => {
        console.log(error);
    });
}

// const createPeer = async () => {
//         navigator.mediaDevices.getUserMedia({ video: true, audio: true }).then((stream) => {
//             streamvideo.value = stream;
            

//             return new SimplePeer({
//                 initiator: true,
//                 trickle: false,
//                 stream: stream,
//                 config: {
//                     iceServers: [
//                             {
//                                 urls: "stun:stun.stunprotocol.org"
//                             },
//                             {
//                                 urls: 'turn:numb.viagenie.ca',
//                                 credential: 'muazkh',
//                                 username: 'webrtc@live.com'
//                             },
//                     ],
//                 },

//             });

//       })
//       .catch((error) => {
//         console.log(error);
//     });
// }

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
    videoCallParams.value.channel.listen("VideoChat", ({ data }) => {
        if (data.type === "incomingCall") {
            // add a new line to the sdp to take care of error
            const updatedSignal = {
            ...data.signalData,
            sdp: `${data.signalData.sdp}\n`,
            };
            console.log('e incoming call?')
            videoCallParams.value.receivingCall = true;
            videoCallParams.value.caller = data.from;
            videoCallParams.value.callerSignal = updatedSignal;
        }
    });
}


const placeVideoCall = (id, name) => {
    callPlaced.value = true;
    callPartner.value = name;


    
    // await getMediaPermission().then((stream) => {

        // console.log(stream)



        peerUser1.value = new SimplePeer({
            initiator: true,
            trickle: false,
            stream: streamvideo.value,
            config: {
            iceServers: [
                    {
                        urls: "stun:stun.stunprotocol.org"
                    },
                    {
                        urls: 'turn:numb.viagenie.ca',
                        credential: 'muazkh',
                        username: 'webrtc@live.com'
                    },
            ],
            },

        });

    setTimeout(() => {
        userVideo.value.srcObject = streamvideo.value;
        userVideo.value.setAttribute('autoplay', '');

    }, 1000)

        console.log(peerUser1.value, 'peerUser1.value')

        // peerUser1.value._debug = console.log

        peerUser1.value.on("signal", (data) => {
            console.log('on signal l data', data)
            // send user call signal
            axios
                .post("/video/call-user", {
                    user_to_call: id,
                    signal_data: data,
                    from: authuserid,
                })
                .then(() => {console.log('axios posted')})
                .catch((error) => {
                    console.log(error, 'axios error');
                });
                
        });

        peerUser1.value.on("stream", (stream) => {
            console.log("call streaming");
            if (partnerVideo) {
                console
                partnerVideo.value.srcObject = stream;
                partnerVideo.value.setAttribute('autoplay', '');
            }
        });

        peerUser1.value.on("connect", () => {
            console.log("peer connected");
        });

        peerUser1.value.on("error", (err) => {
            console.log(err, 'error on 1');
        });

        peerUser1.value.on("close", () => {
            console.log("call closed caller");
        });
    // });

    videoCallParams.value.channel.listen("VideoChat", ({ data }) => {
        if (data.type === "callAccepted") {
            console.log('call accepted 1')
            console.log(data, 'signal we are receiving from peer2')
            videoCallParams.value.callAccepted = true;

             const updatedSignal = {...data.signal, sdp: `${data.signal.sdp}\n`,};
             console.log({updatedSignal})
            peerUser1.value.signal(updatedSignal);
            // if (data.signal.renegotiate) {
            //     console.log("renegotating");
            // }
            // if (data.signal.sdp) {
            //     console.log('SPD')
            //     videoCallParams.value.callAccepted = true;
            //     const updatedSignal = {
            //         ...data.signal,
            //         sdp: `${data.signal.sdp}\n`,
            //     };
            //     console.log({updatedSignal});
            //     peerUser1.value.signal(updatedSignal);
            // }
        }
    });
}

const acceptCall = async () => {
    callPlaced.value = true;
    videoCallParams.value.callAccepted = true;
    // await getMediaPermission();
    peerUser2.value = new SimplePeer({
        initiator: false,
        trickle: false,
        stream: streamvideo.value,
        config: {
          iceServers: [
                {
                    urls: "stun:stun.stunprotocol.org"
                },
                {
                    urls: 'turn:numb.viagenie.ca',
                    credential: 'muazkh',
                    username: 'webrtc@live.com'
                },
          ],
        },
    });

    setTimeout(() => {
        userVideo.value.srcObject = streamvideo.value;
        userVideo.value.setAttribute('autoplay', '');

    }, 1000)
    console.log(peerUser2.value, 'peer 2 init');

    videoCallParams.value.receivingCall = false;
    peerUser2.value.on("signal", (data) => {
        axios
            .post("/video/accept-call", {
                signal: data,
                to: videoCallParams.value.caller
            })
            .then(() => {console.log('axios sent out for 2')})
            .catch((error) => {
            console.error(error);
        });
    });

    peerUser2.value.on("stream", (stream) => {
        videoCallParams.value.callAccepted = true;
        console.log('streaming 2')
        partnerVideo.value.srcObject = stream;
        partnerVideo.value.setAttribute('autoplay', '');
    });

    peerUser2.value.on("connect", () => {
        console.log("peer connected 2");
        videoCallParams.value.callAccepted = true;
    });

    peerUser2.value.on("error", (err) => {
        console.log(err, 'error on 2');
    });

    peerUser2.value.on("close", () => {
        console.log("call closed accepter2");
    });

    console.log(videoCallParams.value.callerSignal, ' signal being sent to peer1 maybe event')
    peerUser2.value.signal(videoCallParams.value.callerSignal);
}

const toggleCameraArea = () => {
    if (videoCallParams.value.callAccepted) {
        isFocusMyself.value = !isFocusMyself;
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
    let streamVid = videoElem.srcObject;
    let tracks = streamVid.getTracks();
    tracks.forEach((track) => {
        track.stop();
    });
    videoElem.srcObject = null;
}


const endCall = () => {
    // if video or audio is muted, enable it so that the stopStreamedVideo method will work
    if (!mutedVideo) toggleMuteVideo();
    if (!mutedAudio) toggleMuteAudio();

    stopStreamedVideo(userVideo.value);
    if (authuserid === videoCallParams.value.caller) {
        console.log('peerUser1.value ending', peerUser1.value)
        peerUser1.value.destroy();
    } else {
        peerUser2.value.destroy();
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




// const handleChat = () => {
//         Echo.channel(`chat_${user_id}`)
//         .listen('TestEvent', (e) => {
//             console.log(e);
//             console.log('read without refresh');
//         });
// }


</script>
