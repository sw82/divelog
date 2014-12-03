var express = require('express');
var router = express.Router();

/* GET home page. */
router.get('/', function(req, res) {
  res.render('index', { title: 'Express' });
});

/* GET New User page. */
router.get('/newlocation', function(req, res) {
    res.render('location', { title: 'Add New Location' });
});

/* GET Userlist page. */
router.get('/locationlist', function(req, res) {
    var db = req.db;
    var collection = db.get('locationcollection');
    collection.find({},{},function(e,docs){
        res.render('locationlist', {
            "locationlist" : docs
        });
    });
});

/* POST to Add User Service */
router.post('/addlocation', function(req, res) {

    // Set our internal DB variable
    var db = req.db;

    // Get our form values. These rely on the "name" attributes
    var locationName = req.body.locationname;
    var locationDate = req.body.locationdate;

    // Set our collection
    var collection = db.get('locationcollection');

    // Submit to the DB
    collection.insert({
        "locationname" : locationName,
        "locationdate" : locationDate
    }, function (err, doc) {
        if (err) {
            // If it failed, return error
            res.send("There was a problem adding the information to the database.");
        }
        else {
            // If it worked, set the header so the address bar doesn't still say /adduser
            res.location("locationlist");
            // And forward to success page
            res.redirect("locationlist");
        }
    });
});


module.exports = router;
