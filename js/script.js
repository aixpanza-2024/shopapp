function searchFunction() {
    var input, filter, div, a, i;
    input = document.getElementById("searchInput");
    filter = input.value.toUpperCase();
    div = document.getElementById("dropdown");
  
    // AJAX request
    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
      if (this.readyState == 4 && this.status == 200) {
        console.log("enterd")
        var items = JSON.parse(this.responseText);
        console.log(items);
        div.innerHTML = ''; // Clear dropdown content
        items.forEach(function(item) {
          var option = document.createElement('a');
          option.href = '#';
          option.textContent = item;
          option.addEventListener('click', function() {
            input.readOnly = true; 
            input.value = item; // Set textbox value when an item is clicked
            div.classList.remove('show'); // Hide the dropdown after selection
          });
          div.appendChild(option);
        });
        div.classList.add('show'); // Show the dropdown
      }
    };
    xhr.open("GET", "../getproduct.php?term=" + filter, true);
    xhr.send();
  }


  function searchinputname()
  {


    const prodname = document.getElementById("searchname").value;//continue connecting to productajax page 

    // AJAX call using jQuery
    $.ajax({

      

      url: '../productajax.php', // Your server endpoint
      type: 'GET',
      data:  { prodname: divId },
      success: function(response) {
          console.log("sucesss");
        $('#printcategory').html(response);

      },
      error: function(error) {
        console.error('Error Status: ' + status); // e.g. 'error'
      console.error('Error Thrown: ' + error);  // e.g. the actual error message
      console.error('Response: ' + xhr.responseText);  
      }
    });

  }



  function searchinputcode()
  {


    const prodname1 = document.getElementById("searchcode").value;//continue connecting to productajax page 
alert("Hello " + prodname1);

    // AJAX call using jQuery
    $.ajax({

      

      url: '../productajax.php', // Your server endpoint
      type: 'GET',
      data:  { prodname: divId },
      success: function(response) {
          console.log("sucesss");
        $('#printcategory').html(response);

      },
      error: function(error) {
        console.error('Error Status: ' + status); // e.g. 'error'
      console.error('Error Thrown: ' + error);  // e.g. the actual error message
      console.error('Response: ' + xhr.responseText);  
      }
    });

  }
  