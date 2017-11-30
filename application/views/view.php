<div id="view-wrapper">
  <video id="player" width="640" height="480" controls>
    <source src="/storage/<?php echo $videoId ?>.mp4" type="video/mp4">
  </video>

  <h3>Transcript</h3>
  <div id="transcript">
  </div>

  <!-- Modal for updating transcript -->
  <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 class="modal-title" id="myModalLabel">Update Transcript</h4>
        </div>
        <div class="modal-body">
          <input id="txt-word"
            class="form-control"
            value="" />
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" onClick="onUpdateWord()">Save changes</button>
        </div>
      </div>
    </div>
  </div>
</div>

<style type="text/css">
  #transcript span {
    cursor: pointer;
  }

  .highlighted-script {
    font-weight: bold;
  }

  .passed-script {
    color: lightgray;
  }
</style>

<script>
  const videoId = <?php echo $videoId ?>;
  let transcript = [];
  let phraseStartIndex, phraseLength;

  loadTranscript();
  setInterval(renderTranscript, 1000);

  function onClickWord(index) {
    const player = $("#player").get(0);
    player.pause();
    player.currentTime = transcript[index].t + 0.5;
    renderTranscript();

    phraseStartIndex = null;
    phraseLength = 0;
    const editingWords = transcript.filter((word, index) => {
      if (word.t >= player.currentTime - 1 && word.t < player.currentTime + 1.5) {
        phraseStartIndex = phraseStartIndex === null ? index : phraseStartIndex;
        phraseLength++;
        return true;
      }

      return false;
    });

    const editingPhrase = editingWords.map(word => word.w).join(' ');
    $("#myModal #txt-word").val(editingPhrase);
    $('#myModal').modal('show');
  }

  function onUpdateWord() {
    transcript[phraseStartIndex].w = $("#myModal #txt-word").val();
    transcript.splice(phraseStartIndex + 1, phraseLength - 1);

    $.ajax({
      url: `/api/videos/${videoId}`,
      method: "POST",
      data: {
        transcript: JSON.stringify(transcript),
      },
      success: () => {
        loadTranscript();
        $('#myModal').modal('hide');
      }
    })
  }

  function renderTranscript() {
    let html = '';

    const player = $("#player").get(0);
    const currentTime = player.currentTime;

    for (let i = 0; i < transcript.length; i ++) {
      let className = '';
      if (transcript[i].t < currentTime - 1) {
        className = 'passed-script';
      } else if (transcript[i].t < currentTime + 1.5) {
        className = 'highlighted-script';
      }

      let wordHtml = `<span class="${className}" onClick="onClickWord(${i})">${transcript[i].w}</span> `
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
