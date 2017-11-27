<div id="index-wrapper">
    <div class="import-form">
        <div class="form-group">
            <label for="txt-url">Paste video URL here:</label>
            <input id="txt-url"
                class="form-control"
                value="https://storage.googleapis.com/hbm-speech-test/ted.mp4" />
        </div>
        <button type="submit"
            class="btn btn-primary"
            onClick="onClickImportVideo()">Submit</button>
    </div>

    <div>
        <h3>Videos</h3>
        <div id="video-list">
        </div>
    </div>
</div>

<style type="text/css">
    .import-form #txt-url {
        width: 100%;
    }
</style>

<script type="text/javascript">
    loadVideoList();
    setInterval(loadVideoList, 5000);

    function onClickImportVideo() {
        const url = $("#txt-url").val();

        if (!url) {
            alert('Please enter a url');
        }

        $("#txt-url").val("");

        $.ajax({
            url: '/api/videos',
            method: 'POST',
            data: {
                url: url,
            },
            success: () => {
                loadVideoList();
            }
        })
    }

    function loadVideoList() {
        $.ajax({
            url: '/api/videos/index',
            success: (response) => {
                let html = '';
                response.forEach((video) => {
                    const viewButton = `
                        <a href="/app/view/${video.id}">View</a>
                    `;

                    html += `
                        <div class="row">
                            <div class="col-xs-10">
                                ${video.url}
                            </div>
                            <div class="col-xs-2">
                                ${video.status === 'done' ? viewButton : video.status}
                            </div>
                        </div>
                    `;
                });

                $("#video-list").html(html);
            }
        });
    }
</script>
