function showAnalyzeForm() {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>SemanticSEO Pro Analyzer</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
    </head>
    <body class="p-4">
        <div class="container">
            <h1>SemanticSEO Pro Analyzer</h1>
            <form id="analyze-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label>Input Type</label>
                    <select id="input-type" name="input_type" class="form-control">
                        <option value="text">Text</option>
                        <option value="url">URL</option>
                        <option value="bulk">Bulk URLs</option>
                    </select>
                </div>
                <div class="form-group" id="text-input-group">
                    <label>Enter Text</label>
                    <textarea name="content" class="form-control" rows="6"></textarea>
                </div>
                <div class="form-group d-none" id="url-input-group">
                    <label>Enter URL</label>
                    <input type="url" name="url" class="form-control">
                </div>
                <div class="form-group d-none" id="bulk-input-group">
                    <label>Enter Bulk URLs (one per line, max 20)</label>
                    <textarea name="bulk_urls" class="form-control" rows="6"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Analyze</button>
            </form>
            <div id="loading" class="text-center mt-4 d-none">
                <div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div>
            </div>
            <div id="results" class="mt-4 d-none">
                <ul class="nav nav-tabs" id="resultsTabs" role="tablist">
                    <li class="nav-item"><a class="nav-link active" id="entities-tab" data-toggle="tab" href="#entities" role="tab">Entities</a></li>
                    <li class="nav-item"><a class="nav-link" id="topics-tab" data-toggle="tab" href="#topics" role="tab">Topics</a></li>
                    <li class="nav-item"><a class="nav-link" id="categories-tab" data-toggle="tab" href="#categories" role="tab">Categories</a></li>
                    <li class="nav-item"><a class="nav-link" id="visualization-tab" data-toggle="tab" href="#visualization" role="tab">Visualization</a></li>
                </ul>
                <div class="tab-content" id="resultsTabsContent">
                    <div class="tab-pane fade show active" id="entities" role="tabpanel">
                        <table id="entities-table" class="table table-striped table-bordered" style="width:100%"><thead><tr><th>Entity</th><th>Type</th><th>Relevance</th></tr></thead><tbody></tbody></table>
                    </div>
                    <div class="tab-pane fade" id="topics" role="tabpanel">
                        <table id="topics-table" class="table table-striped table-bordered" style="width:100%"><thead><tr><th>Topic</th><th>Score</th></tr></thead><tbody></tbody></table>
                    </div>
                    <div class="tab-pane fade" id="categories" role="tabpanel">
                        <table id="categories-table" class="table table-striped table-bordered" style="width:100%"><thead><tr><th>Category</th><th>Score</th></tr></thead><tbody></tbody></table>
                    </div>
                    <div class="tab-pane fade" id="visualization" role="tabpanel">
                        <canvas id="entities-chart" height="100"></canvas>
                    </div>
                </div>
                <button id="export-csv" class="btn btn-secondary mt-3">Export CSV</button>
            </div>
            <div id="bulk-results" class="mt-4"></div>
        </div>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.10.25/js/dataTables.bootstrap4.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        $(function(){
            $('#input-type').on('change',function(){
                var v=$(this).val();
                $('#text-input-group,#url-input-group,#bulk-input-group').addClass('d-none');
                if(v==='text')$('#text-input-group').removeClass('d-none');
                if(v==='url')$('#url-input-group').removeClass('d-none');
                if(v==='bulk')$('#bulk-input-group').removeClass('d-none');
            });
            var entitiesTable=$('#entities-table').DataTable();
            var topicsTable=$('#topics-table').DataTable();
            var categoriesTable=$('#categories-table').DataTable();
            var chart;
            $('#analyze-form').on('submit',function(e){
                e.preventDefault();
                $('#results').addClass('d-none');
                $('#bulk-results').empty();
                $('#loading').removeClass('d-none');
                $.ajax({
                    url:'',
                    method:'POST',
                    data:$(this).serialize(),
                    dataType:'json'
                }).done(function(data){
                    $('#loading').addClass('d-none');
                    if(data.error){
                        alert(data.error);
                        return;
                    }
                    if(data.entities){
                        populateResults(data);
                        $('#results').removeClass('d-none');
                    } else {
                        $.each(data, function(url, resultData){
                            var card = $('<div class="card mb-3"></div>');
                            var header = $('<div class="card-header"></div>').text(url);
                            var body = $('<div class="card-body"></div>');
                            var pre = $('<pre></pre>').text(JSON.stringify(resultData, null, 2));
                            body.append(pre);
                            card.append(header, body);
                            $('#bulk-results').append(card);
                        });
                    }
                }).fail(function(xhr){
                    $('#loading').addClass('d-none');
                    var err=(xhr.responseJSON&&xhr.responseJSON.error)?xhr.responseJSON.error:'Error occurred';
                    alert(err);
                });
            });
            function populateResults(data){
                entitiesTable.clear();
                topicsTable.clear();
                categoriesTable.clear();
                var ents=data.entities||[];
                ents.sort((a,b)=>b.relevance-a.relevance);
                ents.forEach(function(e){entitiesTable.row.add([e.entityId||e.entity,e.type,e.relevance.toFixed(3)]);});
                (data.topics||[]).forEach(function(t){topicsTable.row.add([t.label||t.topicId,t.score.toFixed(3)]);});
                (data.categories||[]).forEach(function(c){categoriesTable.row.add([c.label||c.categoryId,c.score.toFixed(3)]);});
                entitiesTable.draw();
                topicsTable.draw();
                categoriesTable.draw();
                var top=ents.slice(0,10);
                var labels=top.map(e=>e.entityId||e.entity);
                var values=top.map(e=>e.relevance.toFixed(3));
                if(chart)chart.destroy();
                var ctx=document.getElementById('entities-chart').getContext('2d');
                chart=new Chart(ctx,{type:'bar',data:{labels:labels,datasets:[{label:'Relevance',data:values,backgroundColor:'rgba(54,162,235,0.6)'}]}});
            }
            $('#export-csv').on('click',function(){
                var rows=[['Entity','Type','Relevance']];
                entitiesTable.rows().every(function(){rows.push(this.data());});
                rows.push([]);
                rows.push(['Topic','Score']);
                topicsTable.rows().every(function(){rows.push(this.data());});
                rows.push([]);
                rows.push(['Category','Score']);
                categoriesTable.rows().every(function(){rows.push(this.data());});
                var csv=rows.map(r=>r.join(',')).join('\n');
                var blob=new Blob([csv],{type:'text/csv;charset=utf-8;'}),link=document.createElement('a');
                link.href=URL.createObjectURL(blob);
                link.download='analysis.csv';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        });
        </script>
    </body>
    </html>
    <?php
}
function submitAnalyze(){
    header('Content-Type: application/json');
    if(!isset($_POST['csrf_token'])||$_POST['csrf_token']!==$_SESSION['csrf_token']){
        http_response_code(403);
        echo json_encode(['error'=>'Invalid CSRF token']);
        exit;
    }
    $type=$_POST['input_type']??'';
    if($type==='text'){
        $content=trim($_POST['content']??'');
        if($content===''){
            http_response_code(400);
            echo json_encode(['error'=>'Text is required']);
            exit;
        }
        $result=callTextRazor(['text'=>$content]);
        echo json_encode($result);
    } elseif($type==='url'){
        $url=trim($_POST['url']??'');
        if(!filter_var($url,FILTER_VALIDATE_URL)){
            http_response_code(400);
            echo json_encode(['error'=>'Valid URL is required']);
            exit;
        }
        $result=callTextRazor(['url'=>$url]);
        echo json_encode($result);
    } elseif($type==='bulk'){
        $lines=preg_split('/\r\n|\r|\n/',trim($_POST['bulk_urls']??''));
        $urls=array_filter(array_map('trim',$lines));
        if(empty($urls)){
            http_response_code(400);
            echo json_encode(['error'=>'At least one URL is required']);
            exit;
        }
        if(count($urls) > 20){
            http_response_code(400);
            echo json_encode(['error'=>'Maximum of 20 URLs allowed']);
            exit;
        }
        $out=[];
        foreach($urls as $u){
            if(filter_var($u,FILTER_VALIDATE_URL)){
                $out[$u]=callTextRazor(['url'=>$u]);
            } else {
                $out[$u]=['error'=>'Invalid URL'];
            }
        }
        echo json_encode($out);
    } else {
        http_response_code(400);
        echo json_encode(['error'=>'Invalid input type']);
    }
    exit;
}
function callTextRazor($params){
    $client=new Client(['base_uri'=>TEXTRAZOR_API_URL,'timeout'=>30]);
    $body=$params;
    $body['extractors']='entities,topics,categories';
    try{
        $response=$client->post('',[
            'headers'=>['x-textrazor-key'=>TEXTRAZOR_API_KEY],
            'form_params'=>$body
        ]);
        $data=json_decode($response->getBody(),true);
        return $data['response']??[];
    } catch(\Exception $e){
        error_log('TextRazor API error: '.$e->getMessage());
        return ['error'=>'An error occurred while processing the request'];
    }
}