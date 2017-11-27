<div id="view-wrapper">
    <video id="player" width="640" height="480" controls>
      <source src="/storage/<?php echo $videoId ?>.mp4" type="video/mp4">
    </video>

    <div id="edit-area">
        <div class="form-group">
            <label for="txt-url">Edit word:</label>
            <input id="txt-word"
                class="form-control"
                value="" />
        </div>
        <button type="submit"
            class="btn btn-primary"
            onClick="onUpdateWord()">Update</button>
    </div>

    <h3>Transcript</h3>
    <div id="transcript">
    </div>
</div>

<style type="text/css">
    #edit-area {
        display: none;
    }

    #transcript span {
        cursor: pointer;
    }
</style>

<script>
    const videoId = <?php echo $videoId ?>;
    let transcript = [];
    let currentWordIndex = null;

    loadTranscript();

    function onClickWord(index) {
        currentWordIndex = index;

        const player = $("#player").get(0);
        player.currentTime = transcript[index + 1];

        $("#edit-area").css("display", "block");
        $("#edit-area #txt-word").val(transcript[index]);
    }

    function onUpdateWord() {
        transcript[currentWordIndex] = $("#edit-area #txt-word").val();

        $.ajax({
            url: `/api/videos/${videoId}`,
            method: "POST",
            data: {
                transcript: JSON.stringify(transcript),
            },
            success: () => {
                loadTranscript();
                $("#edit-area").css("display", "none");
            }
        })
    }

    function renderTranscript() {
        let html = '';
        for (let i = 0; i < transcript.length; i += 2) {
            let wordHtml = `<span onClick="onClickWord(${i})">${transcript[i]}</span> `
            html += wordHtml;
        }

        $("#transcript").html(html);
    }

    function loadTranscript() {
        $.ajax({
            url: '/api/videos/' + videoId,
            success: (data) => {
                transcript = JSON.parse(data.transcript);
                renderTranscript();

            },
        });
    }
</script>
