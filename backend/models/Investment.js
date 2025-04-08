const mongoose = require('mongoose');

const investmentSchema = new mongoose.Schema({
  user: {
    type: mongoose.Schema.Types.ObjectId,
    ref: 'User',
    required: true
  },
  packageId: {
    type: Number,
    required: true
  },
  amount: {
    type: Number,
    required: true
  },
  dailyProfit: {
    type: Number,
    required: true
  },
  totalProfit: {
    type: Number,
    default: 0
  },
  contractDuration: {
    type: Number,
    default: 100
  },
  startDate: {
    type: Date,
    default: Date.now
  },
  endDate: {
    type: Date,
    required: true
  },
  status: {
    type: String,
    enum: ['active', 'completed', 'terminated'],
    default: 'active'
  },
  lastProfitUpdate: {
    type: Date,
    default: Date.now
  }
});

module.exports = mongoose.model('Investment', investmentSchema);