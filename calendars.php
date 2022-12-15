<?php
require('../include/head.php');

$calendarList = $service->calendarList->listCalendarList();

?>

<body>

    <div class="container mt-5">
        <!-- Button trigger modal -->
        <button type="button" class="btn btn-primary mb-3" data-toggle="modal" data-target="#exampleModalCenter">
            Add Calender
        </button>

        <table id="myTable" class="display">


            <thead>
                <tr>
                    <th>#</th>
                    <th>Sumary</th>
                    <th>Access role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>

                <?php
                $i = 1;
                foreach ($calendarList->getItems() as $calendarListEntry) {
                    $id = urlencode($calendarListEntry->getId());
                ?>

                    <tr>

                        <td><?= $i ?></td>
                        <td><a href="events.php?calendarId=<?= $id ?>"><?= $calendarListEntry->getSummary() ?></a></td>
                        <td><?= $calendarListEntry->getAccessRole() ?></td>
                        <td> <a href="../code\calendar\delete.php?calendarId=<?= $id ?>" class="btn btn-danger"><i class="fa fa-trash"></i></a>
                            | <a href="editCalendar.php?id=<?= $id ?>" class="btn btn-info"><i class="fa-solid fa-pen-to-square"></i></i></a>
                        </td>
                    </tr>

                <?php
                    $i++;
                }
                ?>

            </tbody>
        </table>
    </div>








    <!-- Modal -->
    <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalCenterTitle">Modal title</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="createCalender" class="row g-3">
                        <div class="col-md-6">
                            <label for="summary" class="form-label">Summary</label>
                            <input type="text" class="form-control" id="summary" required>
                        </div>
                        <div class="col-md-6">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="description">
                        </div>

                        <div class="col-md-12 mt-2">
                            <button type="submit" class="btn btn-primary">Create</button>
                        </div>

                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

</body>


<script>
    $(document).ready(function() {

        // Create a new calenders
        $('#createCalender').submit(function(e) {
            e.preventDefault();


            var summary = $('#summary').val();
            var description = $('#description').val();

            // console.log(summary);
            // console.log(description);

            $.ajax({
                type: "POST",
                url: '../code/calendar/create.php',
                data: {
                    "summary": summary,
                    "description": description
                },
                success: function(response) {
                    console.log(response);
                    window.location.reload();
                },
                error: function(response) {
                    console.log(response);
                }
            });
        });

    });
</script>

<?php
require('../include/foot.php');
?>